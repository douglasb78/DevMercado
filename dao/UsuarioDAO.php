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
            'SELECT id, nome, email, senha, is_supplier, is_admin, telefone, cartaocredito, endereco, criado_em
               FROM usuarios
              WHERE email = :email
                AND is_deleted = false
              LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ? Usuario::fromRow($row) : null;
    }

    public function buscarPorId(int $id): ?Usuario {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, email, senha, is_supplier, is_admin, telefone, cartaocredito, endereco, criado_em,
                    cpf, cep, endereco_logradouro, endereco_numero, endereco_complemento,
                    endereco_bairro, endereco_cidade, endereco_uf
               FROM usuarios
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Usuario::fromRow($row) : null;
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
        ?string $cartaocredito = null,
        ?string $endereco = null
    ): Usuario {
        
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios (nome, email, senha, is_supplier, telefone, cartaocredito, endereco)
             VALUES (:nome, :email, :senha, :is_supplier, :telefone, :cartaocredito, :endereco)
             RETURNING id, nome, email, senha, is_supplier, is_admin, telefone, cartaocredito, endereco, criado_em'
        );
        
        $stmt->execute([
            ':nome'         => $nome,
            ':email'        => $email,
            ':senha'        => $senhaHash,
            ':is_supplier'  => $isSupplier ? 'true' : 'false',
            ':telefone'     => $telefone ?? '0',
            ':cartaocredito'=> $cartaocredito ?? '0',
            ':endereco'     => $endereco ?? '',
        ]);
        
        $row = $stmt->fetch();
        return Usuario::fromRow($row);
    }

    public function atualizar(
        int $id,
        string $email,
        ?string $senhaHash = null,
        ?string $telefone = null,
        ?string $cartaocredito = null,
        ?string $endereco = null,
        ?string $nome = null,
        ?bool $isSupplier = null,
        ?string $cpf = null,
        ?string $cep = null,
        ?string $enderecoLogradouro = null,
        ?string $enderecoNumero = null,
        ?string $enderecoComplemento = null,
        ?string $enderecoBairro = null,
        ?string $enderecoCidade = null,
        ?string $enderecoUf = null
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
        if ($endereco !== null) {
            $campos[] = 'endereco = :endereco';
            $params[':endereco'] = $endereco;
        }
        if ($nome !== null) {
            $campos[] = 'nome = :nome';
            $params[':nome'] = $nome;
        }
        if ($isSupplier !== null) {
            $campos[] = 'is_supplier = :is_supplier';
            $params[':is_supplier'] = $isSupplier ? 'true' : 'false';
        }
        if ($cpf !== null) {
            $campos[] = 'cpf = :cpf';
            $params[':cpf'] = $cpf;
        }
        if ($cep !== null) {
            $campos[] = 'cep = :cep';
            $params[':cep'] = $cep;
        }
        if ($enderecoLogradouro !== null) {
            $campos[] = 'endereco_logradouro = :logradouro';
            $params[':logradouro'] = $enderecoLogradouro;
        }
        if ($enderecoNumero !== null) {
            $campos[] = 'endereco_numero = :numero';
            $params[':numero'] = $enderecoNumero;
        }
        if ($enderecoComplemento !== null) {
            $campos[] = 'endereco_complemento = :complemento';
            $params[':complemento'] = $enderecoComplemento;
        }
        if ($enderecoBairro !== null) {
            $campos[] = 'endereco_bairro = :bairro';
            $params[':bairro'] = $enderecoBairro;
        }
        if ($enderecoCidade !== null) {
            $campos[] = 'endereco_cidade = :cidade';
            $params[':cidade'] = $enderecoCidade;
        }
        if ($enderecoUf !== null) {
            $campos[] = 'endereco_uf = :uf';
            $params[':uf'] = $enderecoUf;
        }

        $sql = 'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = :id
                RETURNING id, nome, email, senha, is_supplier, is_admin, telefone, cartaocredito, endereco, criado_em,
                          cpf, cep, endereco_logradouro, endereco_numero, endereco_complemento,
                          endereco_bairro, endereco_cidade, endereco_uf';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch();
        return Usuario::fromRow($row);
    }

    public function buscarFornecedores(string $termo): array {
        $like = '%' . $termo . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, email, senha, is_supplier, is_admin, telefone, cartaocredito, endereco, criado_em
               FROM usuarios
              WHERE is_supplier = true
                AND is_deleted = false
                AND (id::text ILIKE :termo OR nome ILIKE :termo OR email ILIKE :termo2)
              ORDER BY nome ASC'
        );
        $stmt->execute([':termo' => $like, ':termo2' => $like]);
        return array_map(fn($r) => Usuario::fromRow($r), $stmt->fetchAll());
    }

        public function listarTodos(int $limite, int $offset): array {
            $stmt = $this->pdo->prepare(
                     'SELECT id, nome, email, senha, is_supplier, is_admin, telefone, cartaocredito, endereco, criado_em
                         FROM usuarios
                        WHERE is_deleted = false
                        ORDER BY id ASC
                        LIMIT :limite OFFSET :offset'
            );
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return array_map(fn($r) => Usuario::fromRow($r), $stmt->fetchAll());
        }

        public function contarTodos(): int {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM usuarios WHERE is_deleted = false');
            return (int) $stmt->fetchColumn();
        }
    public function buscarPaginado(string $termo, int $limite, int $offset): array {
        $like = '%' . trim($termo) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, email, senha, is_supplier, is_admin, telefone, cartaocredito, endereco, criado_em
               FROM usuarios
              WHERE is_deleted = false
                AND (id::text ILIKE :termo OR nome ILIKE :termo OR email ILIKE :termo2)
              ORDER BY id ASC
              LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue(':termo',  $like);
        $stmt->bindValue(':termo2', $like);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => Usuario::fromRow($r), $stmt->fetchAll());
    }

    public function contarBusca(string $termo): int {
        $like = '%' . trim($termo) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM usuarios
              WHERE is_deleted = false
                AND (id::text ILIKE :termo OR nome ILIKE :termo OR email ILIKE :termo2)'
        );
        $stmt->execute([':termo' => $like, ':termo2' => $like]);
        return (int) $stmt->fetchColumn();
    }

    public function softDelete(int $id): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET is_deleted = true WHERE id = :id AND is_deleted = false'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
