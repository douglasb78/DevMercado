<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../model/Usuario.php';

class UsuarioDAO {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function buscarPorEmail(string $email): ?Usuario {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, email, senha, is_supplier, telefone, cartaocredito, criado_em
               FROM usuarios
              WHERE email = :email
              LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ? new Usuario($row) : null;
    }

    public function buscarPorId(int $id): ?Usuario {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, email, senha, is_supplier, telefone, cartaocredito, criado_em
               FROM usuarios
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? new Usuario($row) : null;
    }

    public function emailJaExiste(string $email): bool {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        return (bool) $stmt->fetch();
    }

    public function inserir(
        string $nome, 
        string $email, 
        string $senhaHash, 
        bool $isSupplier,
        ?string $telefone = null,
        ?string $cartaocredito = null
    ): Usuario {
        
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios (nome, email, senha, is_supplier, telefone, cartaocredito)
             VALUES (:nome, :email, :senha, :is_supplier, :telefone, :cartaocredito)
             RETURNING id, nome, email, senha, is_supplier, telefone, cartaocredito, criado_em'
        );
        
        $stmt->execute([
            ':nome'         => $nome,
            ':email'        => $email,
            ':senha'        => $senhaHash,
            ':is_supplier'  => $isSupplier ? 'true' : 'false',
            ':telefone'     => $telefone ?? '0',
            ':cartaocredito'=> $cartaocredito ?? '0',
        ]);
        
        $row = $stmt->fetch();
        return new Usuario($row);
    }

    public function atualizar(
        int $id,
        string $email,
        ?string $senhaHash = null,
        ?string $telefone = null,
        ?string $cartaocredito = null,
        ?string $nome = null,
        ?bool $isSupplier = null
    ): Usuario {
        $campos = ['email = :email'];
        $params = [':email' => $email, ':id' => $id];

        if ($senhaHash !== null) {
            $campos[] = 'senha = :senha';
            $params[':senha'] = $senhaHash;
        }
        if ($telefone !== null) {
            $campos[] = 'telefone = :telefone';
            $params[':telefone'] = $telefone;
        }
        if ($cartaocredito !== null) {
            $campos[] = 'cartaocredito = :cartaocredito';
            $params[':cartaocredito'] = $cartaocredito;
        }
        if ($nome !== null) {
            $campos[] = 'nome = :nome';
            $params[':nome'] = $nome;
        }
        if ($isSupplier !== null) {
            $campos[] = 'is_supplier = :is_supplier';
            $params[':is_supplier'] = $isSupplier ? 'true' : 'false';
        }

        $sql = 'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = :id
                RETURNING id, nome, email, senha, is_supplier, telefone, cartaocredito, criado_em';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $row = $stmt->fetch();
        return new Usuario($row);
    }

    public function buscarFornecedores(string $termo): array {
        $like = '%' . $termo . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, email, senha, is_supplier, telefone, cartaocredito, criado_em
               FROM usuarios
              WHERE is_supplier = true
                AND (nome ILIKE :termo OR email ILIKE :termo2)
              ORDER BY nome ASC'
        );
        $stmt->execute([':termo' => $like, ':termo2' => $like]);
        return array_map(fn($r) => new Usuario($r), $stmt->fetchAll());
    }

    public function listarFornecedores(): array {
        $stmt = $this->pdo->query(
            'SELECT id, nome, email, senha, is_supplier, telefone, cartaocredito, criado_em
               FROM usuarios
              WHERE is_supplier = true
              ORDER BY nome ASC'
        );
        return array_map(fn($r) => new Usuario($r), $stmt->fetchAll());
    }
}