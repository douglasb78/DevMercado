<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../model/Pedido.php';
require_once __DIR__ . '/../model/ItemPedido.php';

class PedidoDAO {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    // ── Leitura ─────────────────────────────────────────────────────────────

    /** Pedidos de um comprador com seus itens */
    public function listarPorComprador(int $compradorId): array {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pedidos
              WHERE comprador_id = :cid
              ORDER BY criado_em DESC'
        );
        $stmt->execute([':cid' => $compradorId]);
        $rows = $stmt->fetchAll();

        $pedidos = [];
        foreach ($rows as $row) {
            $pedido = new Pedido($row);
            $pedido->itens = $this->listarItensDoPedido($pedido->id);
            $pedidos[] = $pedido;
        }
        return $pedidos;
    }

    /** Pedidos cujos produtos pertencem a um fornecedor */
    public function listarPorFornecedor(int $fornecedorId): array {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT p.*, u.nome AS comprador_nome, u.endereco AS comprador_endereco
               FROM pedidos p
               JOIN usuarios u      ON u.id = p.comprador_id
               JOIN itens_pedido ip ON ip.pedido_id = p.id
               JOIN produtos pr     ON pr.id = ip.produto_id
              WHERE pr.fornecedor_id = :fid
              ORDER BY p.criado_em DESC'
        );
        $stmt->execute([':fid' => $fornecedorId]);
        $rows = $stmt->fetchAll();

        $pedidos = [];
        foreach ($rows as $row) {
            $pedido = new Pedido($row);
            $pedido->itens = $this->listarItensDoPedidoPorFornecedor($pedido->id, $fornecedorId);
            $pedidos[] = $pedido;
        }
        return $pedidos;
    }

    /** Itens de um pedido */
    public function listarItensDoPedido(int $pedidoId): array {
        $stmt = $this->pdo->prepare(
            'SELECT ip.*,
                    p.nome     AS produto_nome,
                    p.foto_url AS produto_foto
               FROM itens_pedido ip
               JOIN produtos p ON p.id = ip.produto_id
              WHERE ip.pedido_id = :pid'
        );
        $stmt->execute([':pid' => $pedidoId]);
        return array_map(fn($r) => new ItemPedido($r), $stmt->fetchAll());
    }

    /** Itens de um pedido filtrando por fornecedor */
    public function listarItensDoPedidoPorFornecedor(int $pedidoId, int $fornecedorId): array {
        $stmt = $this->pdo->prepare(
            'SELECT ip.*,
                    p.nome     AS produto_nome,
                    p.foto_url AS produto_foto
               FROM itens_pedido ip
               JOIN produtos p ON p.id = ip.produto_id
              WHERE ip.pedido_id = :pid AND p.fornecedor_id = :fid'
        );
        $stmt->execute([':pid' => $pedidoId, ':fid' => $fornecedorId]);
        return array_map(fn($r) => new ItemPedido($r), $stmt->fetchAll());
    }

    public function buscarPorId(int $id): ?Pedido {
        $stmt = $this->pdo->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $pedido = new Pedido($row);
        $pedido->itens = $this->listarItensDoPedido($id);
        return $pedido;
    }

    // ── Escrita ─────────────────────────────────────────────────────────────

    /**
     * Cria pedido a partir do carrinho do usuário.
     * Decrementa estoque e limpa o carrinho dentro de uma transação.
     *
     * @param  ItemCarrinho[] $itensCarrinho
     */
    public function criarDesdoCarrinho(int $compradorId, array $itensCarrinho): Pedido {
        $this->pdo->beginTransaction();
        try {
            $total = array_sum(array_map(fn($i) => $i->subtotal(), $itensCarrinho));

            // Cria o pedido
            $stmt = $this->pdo->prepare(
                'INSERT INTO pedidos (comprador_id, total)
                 VALUES (:cid, :total)
                 RETURNING *'
            );
            $stmt->execute([':cid' => $compradorId, ':total' => $total]);
            $pedido = new Pedido($stmt->fetch());

            // Insere itens
            $stmtItem = $this->pdo->prepare(
                'INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unit)
                 VALUES (:pid, :prodid, :qtd, :preco)'
            );
            $stmtEst = $this->pdo->prepare(
                'UPDATE produtos
                    SET estoque = estoque - :qtd
                  WHERE id = :id AND estoque >= :qtd2'
            );

            foreach ($itensCarrinho as $item) {
                $stmtItem->execute([
                    ':pid'    => $pedido->id,
                    ':prodid' => $item->produtoId,
                    ':qtd'    => $item->quantidade,
                    ':preco'  => $item->produtoPreco,
                ]);
                $stmtEst->execute([
                    ':qtd'  => $item->quantidade,
                    ':qtd2' => $item->quantidade,
                    ':id'   => $item->produtoId,
                ]);
                if ($stmtEst->rowCount() === 0) {
                    throw new RuntimeException("Estoque insuficiente para: {$item->produtoNome}");
                }
            }

            // Limpa carrinho
            $this->pdo->prepare('DELETE FROM carrinho WHERE usuario_id = :uid')
                      ->execute([':uid' => $compradorId]);

            $this->pdo->commit();
            return $pedido;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Atualiza status e data estimada de um pedido (apenas fornecedor) */
    public function atualizarStatus(int $pedidoId, string $status, ?string $dataEstimada, int $fornecedorId): bool {
        // Verifica se o pedido tem itens do fornecedor
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM itens_pedido ip
               JOIN produtos p ON p.id = ip.produto_id
              WHERE ip.pedido_id = :pid AND p.fornecedor_id = :fid
              LIMIT 1'
        );
        $stmt->execute([':pid' => $pedidoId, ':fid' => $fornecedorId]);
        if (!$stmt->fetch()) return false;

        $stmt = $this->pdo->prepare(
            'UPDATE pedidos
                SET status = :status, data_estimada = :data
              WHERE id = :id'
        );
        return $stmt->execute([
            ':status' => $status,
            ':data'   => $dataEstimada ?: null,
            ':id'     => $pedidoId,
        ]);
    }
}
