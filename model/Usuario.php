<?php
require_once __DIR__ . '/Endereco.php';

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

    public string $cpf;
    public string $cep;
    public string $enderecoLogradouro;
    public string $enderecoNumero;
    public string $enderecoComplemento;
    public string $enderecoBairro;
    public string $enderecoCidade;
    public string $enderecoUf;

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

        $this->cpf                 = $dados['cpf']                 ?? '';
        $this->cep                 = $dados['cep']                 ?? '';
        $this->enderecoLogradouro  = $dados['endereco_logradouro']  ?? '';
        $this->enderecoNumero      = $dados['endereco_numero']      ?? '';
        $this->enderecoComplemento = $dados['endereco_complemento'] ?? '';
        $this->enderecoBairro      = $dados['endereco_bairro']      ?? '';
        $this->enderecoCidade      = $dados['endereco_cidade']      ?? '';
        $this->enderecoUf          = $dados['endereco_uf']          ?? '';
    }

    public static function fromRow(array $dados): Usuario {
        return !empty($dados['is_supplier']) ? new Fornecedor($dados) : new Cliente($dados);
    }

    public function isFornecedor(): bool {
        return $this->isSupplier;
    }

    public function getEndereco(): Endereco {
        return new Endereco([
            'endereco_logradouro'  => $this->enderecoLogradouro,
            'endereco_numero'      => $this->enderecoNumero,
            'endereco_complemento' => $this->enderecoComplemento,
            'endereco_bairro'      => $this->enderecoBairro,
            'endereco_cidade'      => $this->enderecoCidade,
            'endereco_uf'          => $this->enderecoUf,
            'cep'                  => $this->cep,
        ]);
    }
}

require_once __DIR__ . '/Cliente.php';
require_once __DIR__ . '/Fornecedor.php';
