<?php
require_once __DIR__ . '/Usuario.php';

/**
 * Fornecedor (conforme o diagrama de classes do trabalho): nome, descricao,
 * telefone, email, possui um Endereco e fornece Produtos (0..*).
 *
 * É um Usuario marcado como fornecedor (is_supplier = true). O DAO instancia
 * esta subclasse para os usuários que vendem produtos na loja.
 */
class Fornecedor extends Usuario {

    /** Descrição/loja do fornecedor (campo do modelo; opcional no cadastro). */
    public string $descricao;

    public function __construct(array $dados) {
        parent::__construct($dados);
        $this->descricao = $dados['descricao'] ?? '';
    }
}
