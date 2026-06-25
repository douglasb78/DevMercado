<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../model/Pedido.php';
require_once __DIR__ . '/../model/ItemPedido.php';

class EnderecoFaltandoException extends RuntimeException {}

class PedidoDAO {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function listarPorCompradorPaginado(int $compradorId, int $limite, int $offset): array {
        $stmt = $this->pdo->prepare(
                        'SELECT * FROM pedidos
                            WHERE comprador_id = :cid
                            ORDER BY id ASC
                            LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue(':cid', $compradorId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $pedidos = [];
        foreach ($stmt->fetchAll() as $row) {
            $pedido = new Pedido($row);
            $pedido->itens = $this->listarItensDoPedido($pedido->id);
            $pedidos[] = $pedido;
        }
        return $pedidos;
    }

    public function contarPorComprador(int $compradorId): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE comprador_id = :cid');
        $stmt->execute([':cid' => $compradorId]);
        return (int) $stmt->fetchColumn();
    }

    public function listarPorFornecedorPaginado(int $fornecedorId, int $limite, int $offset): array {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT p.*, u.nome AS comprador_nome, u.endereco AS comprador_endereco
               FROM pedidos p
               JOIN usuarios u      ON u.id = p.comprador_id
               JOIN itens_pedido ip ON ip.pedido_id = p.id
               JOIN produtos pr     ON pr.id = ip.produto_id
              WHERE pr.fornecedor_id = :fid
              ORDER BY p.id ASC
              LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue(':fid', $fornecedorId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $pedidos = [];
        foreach ($stmt->fetchAll() as $row) {
            $pedido = new Pedido($row);
            $pedido->itens = $this->listarItensDoPedidoPorFornecedor($pedido->id, $fornecedorId);
            $pedidos[] = $pedido;
        }
        return $pedidos;
    }

    public function contarPorFornecedor(int $fornecedorId): int {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT p.id)
               FROM pedidos p
               JOIN itens_pedido ip ON ip.pedido_id = p.id
               JOIN produtos pr     ON pr.id = ip.produto_id
              WHERE pr.fornecedor_id = :fid'
        );
        $stmt->execute([':fid' => $fornecedorId]);
        return (int) $stmt->fetchColumn();
    }

    public function listarItensDoPedido(int $pedidoId): array {
        $stmt = $this->pdo->prepare(
            'SELECT ip.*,
                    p.nome     AS produto_nome,
                    p.foto_url AS produto_foto,
                    p.fornecedor_id AS fornecedor_id,
                    u.nome AS fornecedor_nome
               FROM itens_pedido ip
               JOIN produtos p ON p.id = ip.produto_id
               JOIN usuarios u ON u.id = p.fornecedor_id
              WHERE ip.pedido_id = :pid
              ORDER BY p.id ASC'
        );
        $stmt->execute([':pid' => $pedidoId]);
        return array_map(fn($r) => new ItemPedido($r), $stmt->fetchAll());
    }

    public function listarItensDoPedidoPorFornecedor(int $pedidoId, int $fornecedorId): array {
        $stmt = $this->pdo->prepare(
            'SELECT ip.*,
                    p.nome     AS produto_nome,
                    p.foto_url AS produto_foto,
                    p.fornecedor_id AS fornecedor_id,
                    u.nome AS fornecedor_nome
               FROM itens_pedido ip
               JOIN produtos p ON p.id = ip.produto_id
               JOIN usuarios u ON u.id = p.fornecedor_id
              WHERE ip.pedido_id = :pid AND p.fornecedor_id = :fid
              ORDER BY p.id ASC'
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

    public function pedidoPertenceAoFornecedor(int $pedidoId, int $fornecedorId): bool {
        $stmt = $this->pdo->prepare(
            'SELECT 1
               FROM itens_pedido ip
               JOIN produtos p ON p.id = ip.produto_id
              WHERE ip.pedido_id = :pid AND p.fornecedor_id = :fid
              LIMIT 1'
        );
        $stmt->execute([':pid' => $pedidoId, ':fid' => $fornecedorId]);
        return (bool) $stmt->fetch();
    }

    public function listarTodosPaginado(int $limite, int $offset): array {
        $stmt = $this->pdo->prepare(
                'SELECT p.*, u.nome AS comprador_nome, u.endereco AS comprador_endereco,
                    string_agg(DISTINCT uf.nome, \', \' ORDER BY uf.nome) AS fornecedores
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
               LEFT JOIN itens_pedido ip ON ip.pedido_id = p.id
               LEFT JOIN produtos pr ON pr.id = ip.produto_id
               LEFT JOIN usuarios uf ON uf.id = pr.fornecedor_id
              GROUP BY p.id, u.nome, u.endereco
              ORDER BY p.id ASC
              LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $pedidos = [];
        foreach ($stmt->fetchAll() as $row) {
            $pedido = new Pedido($row);
            $pedido->itens = $this->listarItensDoPedido($pedido->id);
            $pedidos[] = $pedido;
        }
        return $pedidos;
    }

    public function contarTodos(): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pedidos');
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function buscarPaginado(string $termo, int $limite, int $offset): array {
        $like = '%' . trim($termo) . '%';
        $stmt = $this->pdo->prepare(
                'SELECT p.*, u.nome AS comprador_nome, u.endereco AS comprador_endereco,
                    string_agg(DISTINCT uf.nome, \', \' ORDER BY uf.nome) AS fornecedores
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
               LEFT JOIN itens_pedido ip ON ip.pedido_id = p.id
               LEFT JOIN produtos pr ON pr.id = ip.produto_id
               LEFT JOIN usuarios uf ON uf.id = pr.fornecedor_id
              WHERE p.id::text ILIKE :termo OR u.nome ILIKE :termo OR uf.nome ILIKE :termo
              GROUP BY p.id, u.nome, u.endereco
              ORDER BY p.id ASC
              LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue(':termo', $like);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $pedidos = [];
        foreach ($stmt->fetchAll() as $row) {
            $pedido = new Pedido($row);
            $pedido->itens = $this->listarItensDoPedido($pedido->id);
            $pedidos[] = $pedido;
        }
        return $pedidos;
    }

    public function contarBusca(string $termo): int {
        $like = '%' . trim($termo) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT p.id)
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
               LEFT JOIN itens_pedido ip ON ip.pedido_id = p.id
               LEFT JOIN produtos pr ON pr.id = ip.produto_id
               LEFT JOIN usuarios uf ON uf.id = pr.fornecedor_id
              WHERE p.id::text ILIKE :termo OR u.nome ILIKE :termo OR uf.nome ILIKE :termo'
        );
        $stmt->bindValue(':termo', $like);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function criarDesdoCarrinho(int $compradorId, array $itensCarrinho): Pedido {
        // Verifica se o comprador tem endereço cadastrado
        $stmtEnd = $this->pdo->prepare('SELECT endereco FROM usuarios WHERE id = :id LIMIT 1');
        $stmtEnd->execute([':id' => $compradorId]);
        $row = $stmtEnd->fetch();
        $endereco = $row['endereco'] ?? null;
        if (trim((string)($endereco ?? '')) === '') {
            throw new EnderecoFaltandoException('Cadastre um endereço no seu perfil antes de finalizar a compra.');
        }

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

        // Grava as datas de envio/cancelamento conforme o novo status.
        $sql = 'UPDATE pedidos SET status = :status, data_estimada = :data';
        if ($status === 'saiu') {
            $sql .= ', data_envio = COALESCE(data_envio, CURRENT_DATE)';
        }
        if ($status === 'cancelado') {
            $sql .= ', data_cancelamento = COALESCE(data_cancelamento, CURRENT_DATE)';
        }
        $sql .= ' WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':data'   => $dataEstimada ?: null,
            ':id'     => $pedidoId,
        ]);
    }

    public function atualizarItemQuantidade(int $pedidoId, int $produtoId, int $novaQuantidade): float {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT quantidade, preco_unit FROM itens_pedido WHERE pedido_id = :pid AND produto_id = :prod LIMIT 1'
            );
            $stmt->execute([':pid' => $pedidoId, ':prod' => $produtoId]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new RuntimeException('Item não encontrado no pedido.');
            }

            $quantAtual = (int) $row['quantidade'];

            if ($novaQuantidade <= 0) {
                // remover item e devolver estoque
                $this->pdo->prepare('DELETE FROM itens_pedido WHERE pedido_id = :pid AND produto_id = :prod')
                          ->execute([':pid' => $pedidoId, ':prod' => $produtoId]);
                $this->pdo->prepare('UPDATE produtos SET estoque = estoque + :qtd WHERE id = :id')
                          ->execute([':qtd' => $quantAtual, ':id' => $produtoId]);
            } else {
                $diff = $novaQuantidade - $quantAtual;
                if ($diff > 0) {
                    $stmtStock = $this->pdo->prepare(
                        'UPDATE produtos SET estoque = estoque - :diff WHERE id = :id AND estoque >= :diff'
                    );
                    $stmtStock->execute([':diff' => $diff, ':id' => $produtoId]);
                    if ($stmtStock->rowCount() === 0) {
                        throw new RuntimeException('Estoque insuficiente para ajustar o pedido.');
                    }
                } elseif ($diff < 0) {
                    $this->pdo->prepare('UPDATE produtos SET estoque = estoque + :inc WHERE id = :id')
                              ->execute([':inc' => -$diff, ':id' => $produtoId]);
                }

                $this->pdo->prepare('UPDATE itens_pedido SET quantidade = :qtd WHERE pedido_id = :pid AND produto_id = :prod')
                          ->execute([':qtd' => $novaQuantidade, ':pid' => $pedidoId, ':prod' => $produtoId]);
            }

            // Recalcula total do pedido
            $stmtTotal = $this->pdo->prepare('SELECT COALESCE(SUM(quantidade * preco_unit), 0) FROM itens_pedido WHERE pedido_id = :pid');
            $stmtTotal->execute([':pid' => $pedidoId]);
            $total = (float) $stmtTotal->fetchColumn();

            $this->pdo->prepare('UPDATE pedidos SET total = :total WHERE id = :pid')
                      ->execute([':total' => $total, ':pid' => $pedidoId]);

            $this->pdo->commit();
            return $total;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function atualizarStatusAdmin(int $pedidoId, string $status, ?string $dataEstimada): bool {
        $sql = 'UPDATE pedidos SET status = :status, data_estimada = :data';
        if ($status === 'saiu') {
            $sql .= ', data_envio = COALESCE(data_envio, CURRENT_DATE)';
        }
        $sql .= ' WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':data'   => $dataEstimada ?: null,
            ':id'     => $pedidoId,
        ]) && $stmt->rowCount() > 0;
    }

    public function cancelarPedidoAdmin(int $pedidoId): bool {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT status FROM pedidos WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $pedidoId]);
            $row = $stmt->fetch();
            if (!$row || $row['status'] === 'cancelado') {
                $this->pdo->rollBack();
                return false;
            }

            // Devolve o estoque de cada item do pedido.
            $itens = $this->pdo->prepare('SELECT produto_id, quantidade FROM itens_pedido WHERE pedido_id = :pid');
            $itens->execute([':pid' => $pedidoId]);
            $devolver = $this->pdo->prepare('UPDATE produtos SET estoque = estoque + :qtd WHERE id = :id');
            foreach ($itens->fetchAll() as $it) {
                $devolver->execute([':qtd' => (int) $it['quantidade'], ':id' => (int) $it['produto_id']]);
            }

            $this->pdo->prepare(
                "UPDATE pedidos
                    SET status = 'cancelado',
                        data_cancelamento = COALESCE(data_cancelamento, CURRENT_DATE)
                  WHERE id = :id"
            )->execute([':id' => $pedidoId]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
