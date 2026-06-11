<?php

class Usuario {
    public int    $id;
    public string $nome;
    public string $email;
    public string $senha;
    public string $telefone;
    public string $cartaocredito;
    public string $endereco;
    public bool   $isSupplier;
    public bool   $isAdmin;
    public string $criadoEm;

    public function __construct(array $dados) {
        $this->id            = (int)  $dados['id'];
        $this->nome          =        $dados['nome'];
        $this->email         =        $dados['email'];
        $this->senha         =        $dados['senha'];
        $this->isSupplier    = (bool) $dados['is_supplier'];
        $this->isAdmin       = (bool) ($dados['is_admin'] ?? false);
        $this->telefone      =        $dados['telefone'];
        $this->cartaocredito =		  $dados['cartaocredito'];
        $this->endereco      =        $dados['endereco'] ?? '';
        $this->criadoEm      =        $dados['criado_em'] ?? '';
    }
}
