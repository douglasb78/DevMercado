<?php
require_once __DIR__ . '/Usuario.php';

class Fornecedor extends Usuario {

    public string $descricao;

    public function __construct(array $dados) {
        parent::__construct($dados);
        $this->descricao = $dados['descricao'] ?? '';
    }
}
