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
              ORDER BY p.id ASC'
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

    /** Esvazia o carrinho inteiro */
    public function limpar(int $usuarioId): bool {
        $stmt = $this->pdo->prepare(
            'DELETE FROM carrinho WHERE usuario_id = :uid'
        );
        return $stmt->execute([':uid' => $usuarioId]);
    }

}
