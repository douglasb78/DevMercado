<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../model/ItemCarrinho.php';

class CarrinhoDAO {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    /** Retorna todos os itens do carrinho */
    public function listarPorUsuario(int $usuarioId): array {
        $stmt = $this->pdo->prepare(
            'SELECT c.*,
                    p.nome      AS produto_nome,
                    p.preco     AS produto_preco,
                    p.foto_url  AS produto_foto,
                    p.estoque   AS estoque_disponivel,
                    u.nome      AS fornecedor_nome
               FROM carrinho c
               JOIN produtos p ON p.id = c.produto_id
               JOIN usuarios u ON u.id = p.fornecedor_id
              WHERE c.usuario_id = :uid
              ORDER BY c.adicionado_em DESC'
        );
        $stmt->execute([':uid' => $usuarioId]);
        return array_map(fn($r) => new ItemCarrinho($r), $stmt->fetchAll());
    }

    /** Adiciona item ao carrinho ou incrementa */
    public function adicionarOuIncrementar(int $usuarioId, int $produtoId, int $quantidade = 1): bool {
        $stmt = $this->pdo->prepare(
            'INSERT INTO carrinho (usuario_id, produto_id, quantidade)
             VALUES (:uid, :pid, :qtd)
             ON CONFLICT (usuario_id, produto_id)
             DO UPDATE SET quantidade = carrinho.quantidade + :qtd2'
        );
        return $stmt->execute([
            ':uid'  => $usuarioId,
            ':pid'  => $produtoId,
            ':qtd'  => $quantidade,
            ':qtd2' => $quantidade,
        ]);
    }

    /** Atualiza a quantidade */
    public function atualizarQuantidade(int $usuarioId, int $produtoId, int $quantidade): bool {
        if ($quantidade <= 0) {
            return $this->remover($usuarioId, $produtoId);
        }
        $stmt = $this->pdo->prepare(
            'UPDATE carrinho
                SET quantidade = :qtd
              WHERE usuario_id = :uid AND produto_id = :pid'
        );
        return $stmt->execute([
            ':qtd' => $quantidade,
            ':uid' => $usuarioId,
            ':pid' => $produtoId,
        ]);
    }

    /** Remove item do carrinho */
    public function remover(int $usuarioId, int $produtoId): bool {
        $stmt = $this->pdo->prepare(
            'DELETE FROM carrinho WHERE usuario_id = :uid AND produto_id = :pid'
        );
        return $stmt->execute([':uid' => $usuarioId, ':pid' => $produtoId]);
    }

    /** Esvazia o carrinho inteiro */
    public function limpar(int $usuarioId): bool {
        $stmt = $this->pdo->prepare(
            'DELETE FROM carrinho WHERE usuario_id = :uid'
        );
        return $stmt->execute([':uid' => $usuarioId]);
    }

    /** Calcula o total */
    public function calcularTotal(int $usuarioId): float {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(c.quantidade * p.preco), 0)
               FROM carrinho c
               JOIN produtos p ON p.id = c.produto_id
              WHERE c.usuario_id = :uid'
        );
        $stmt->execute([':uid' => $usuarioId]);
        return (float) $stmt->fetchColumn();
    }

    /** Conta itens no carrinho */
    public function contar(int $usuarioId): int {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantidade), 0) FROM carrinho WHERE usuario_id = :uid'
        );
        $stmt->execute([':uid' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }
}
