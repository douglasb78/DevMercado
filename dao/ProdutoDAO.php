<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../model/Produto.php';

class ProdutoDAO {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function listarTodos(int $limite = 50, int $offset = 0): array {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.nome AS fornecedor_nome
               FROM produtos p
               JOIN usuarios u ON u.id = p.fornecedor_id
              WHERE p.is_deleted = false
              ORDER BY p.id ASC
              LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue(':limite',  $limite,  PDO::PARAM_INT);
        $stmt->bindValue(':offset',  $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => new Produto($r), $stmt->fetchAll());
    }

    public function listarDisponiveis(int $limite = 50, int $offset = 0): array {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.nome AS fornecedor_nome
               FROM produtos p
               JOIN usuarios u ON u.id = p.fornecedor_id
              WHERE p.is_deleted = false AND p.estoque > 0
              ORDER BY p.id ASC
              LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => new Produto($r), $stmt->fetchAll());
    }

    public function listarPorCategoria(string $categoria, int $limite = 50, int $offset = 0): array {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.nome AS fornecedor_nome
               FROM produtos p
               JOIN usuarios u ON u.id = p.fornecedor_id
              WHERE p.categoria = :categoria AND p.is_deleted = false
              ORDER BY p.id ASC
              LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue(':categoria', $categoria);
        $stmt->bindValue(':limite',    $limite,  PDO::PARAM_INT);
        $stmt->bindValue(':offset',    $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => new Produto($r), $stmt->fetchAll());
    }

    public function softDelete(int $id, int $fornecedorId): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE produtos 
            SET is_deleted = true
            WHERE id = :id 
            AND fornecedor_id = :fid 
            AND is_deleted = false'
        );

        return $stmt->execute([
            ':id'  => $id,
            ':fid' => $fornecedorId
        ]);
    }

    public function buscar(string $termo, int $limite = 50, int $offset = 0): array
    {
        $termo = trim($termo);
        $like  = '%' . $termo . '%';

        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.nome AS fornecedor_nome
            FROM produtos p
            JOIN usuarios u ON u.id = p.fornecedor_id
            WHERE (p.id::text ILIKE :termo 
                OR p.nome ILIKE :termo 
                OR p.descricao ILIKE :termo2)
                AND p.is_deleted = false
            ORDER BY p.id ASC
            LIMIT :limite OFFSET :offset'
        );

        $stmt->bindValue(':termo',  $like);
        $stmt->bindValue(':termo2', $like);
        $stmt->bindValue(':limite', $limite,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn($r) => new Produto($r), $stmt->fetchAll());
    }

    public function contarBusca(string $termo): int {
        $like = '%' . $termo . '%';
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM produtos
              WHERE (nome ILIKE :termo OR descricao ILIKE :termo2)
                AND is_deleted = false'
        );
        $stmt->execute([':termo' => $like, ':termo2' => $like]);
        return (int) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?Produto {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.nome AS fornecedor_nome
               FROM produtos p
               JOIN usuarios u ON u.id = p.fornecedor_id
              WHERE p.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? new Produto($row) : null;
    }

    public function listarPorFornecedor(int $fornecedorId): array {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.nome AS fornecedor_nome
               FROM produtos p
               JOIN usuarios u ON u.id = p.fornecedor_id
              WHERE p.fornecedor_id = :fid AND p.is_deleted = false
              ORDER BY p.id ASC'
        );
        $stmt->execute([':fid' => $fornecedorId]);
        return array_map(fn($r) => new Produto($r), $stmt->fetchAll());
    }

    public function listarCategorias(): array {
        $stmt = $this->pdo->query(
            'SELECT categoria, COUNT(*) AS total
               FROM produtos
              WHERE categoria IS NOT NULL AND categoria <> \'\' AND is_deleted = false
              GROUP BY categoria
              ORDER BY categoria'
        );
        return $stmt->fetchAll();
    }

    public function contar(?string $categoria = null): int {
        if ($categoria) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM produtos WHERE categoria = :cat AND is_deleted = false');
            $stmt->execute([':cat' => $categoria]);
        } else {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM produtos WHERE is_deleted = false');
        }
        return (int) $stmt->fetchColumn();
    }

    public function listarPorIds(array $ids): array {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nome AS fornecedor_nome
               FROM produtos p
               JOIN usuarios u ON u.id = p.fornecedor_id
              WHERE p.id IN ($placeholders) AND p.is_deleted = false
              ORDER BY p.id ASC"
        );
        $stmt->execute($ids);
        return array_map(fn($r) => new Produto($r), $stmt->fetchAll());
    }


    public function inserir(
        int    $fornecedorId,
        string $nome,
        string $descricao,
        float  $preco,
        int    $estoque,
        string $categoria,
        string $fotoUrl
    ): Produto {
        $stmt = $this->pdo->prepare(
            'INSERT INTO produtos (fornecedor_id, nome, descricao, preco, estoque, categoria, foto_url)
             VALUES (:fid, :nome, :desc, :preco, :estoque, :cat, :foto)
             RETURNING *'
        );
        $stmt->execute([
            ':fid'     => $fornecedorId,
            ':nome'    => $nome,
            ':desc'    => $descricao,
            ':preco'   => $preco,
            ':estoque' => $estoque,
            ':cat'     => $categoria,
            ':foto'    => $fotoUrl,
        ]);
        $row = $stmt->fetch();
        $row['fornecedor_nome'] = '';
        return new Produto($row);
    }

    public function atualizar(
        int    $id,
        string $nome,
        string $descricao,
        float  $preco,
        string $categoria,
        string $fotoUrl
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE produtos
                SET nome = :nome, descricao = :desc, preco = :preco,
                    categoria = :cat, foto_url = :foto
              WHERE id = :id'
        );
        return $stmt->execute([
            ':id'   => $id,
            ':nome' => $nome,
            ':desc' => $descricao,
            ':preco'=> $preco,
            ':cat'  => $categoria,
            ':foto' => $fotoUrl,
        ]);
    }

    public function atualizarEstoque(int $id, int $novoEstoque): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE produtos SET estoque = :est WHERE id = :id'
        );
        return $stmt->execute([':est' => $novoEstoque, ':id' => $id]);
    }

    public function decrementarEstoque(int $id, int $quantidade): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE produtos
                SET estoque = estoque - :qtd
              WHERE id = :id AND estoque >= :qtd'
        );
        $stmt->execute([':qtd' => $quantidade, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function excluir(int $id, int $fornecedorId): bool {
        $stmt = $this->pdo->prepare(
            'DELETE FROM produtos WHERE id = :id AND fornecedor_id = :fid'
        );
        return $stmt->execute([':id' => $id, ':fid' => $fornecedorId]);
    }
}
