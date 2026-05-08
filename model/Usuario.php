<?php

class Usuario {
    public int    $id;
    public string $nome;
    public string $email;
    public string $senha;
    public string $telefone;
    public string $cartaocredito;
    public bool   $isSupplier;
    public string $criadoEm;

    public function __construct(array $dados) {
        $this->id            = (int)  $dados['id'];
        $this->nome          =        $dados['nome'];
        $this->email         =        $dados['email'];
        $this->senha         =        $dados['senha'];
        $this->isSupplier    = (bool) $dados['is_supplier'];
        $this->telefone      =        $dados['telefone'];
        $this->cartaocredito =		  $dados['cartaocredito'];
        $this->criadoEm      =        $dados['criado_em'] ?? '';
    }
}