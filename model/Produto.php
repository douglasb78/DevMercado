<?php
require_once __DIR__ . '/Estoque.php';

class Produto {
    public int    $id;
    public int    $fornecedorId;
    public string $nome;
    public string $descricao;
    public float  $preco;
    public int    $estoque;
    public string $categoria;
    public string $fotoUrl;
    public string $criadoEm;

    public string $fornecedorNome;

    public function __construct(array $dados) {
        $this->id             = (int)   $dados['id'];
        $this->fornecedorId   = (int)   $dados['fornecedor_id'];
        $this->nome           =         $dados['nome'];
        $this->descricao      =         $dados['descricao']       ?? '';
        $this->preco          = (float) $dados['preco'];
        $this->estoque        = (int)   $dados['estoque'];
        $this->categoria      =         $dados['categoria']       ?? '';
        $this->fotoUrl        =         $dados['foto_url']        ?? '';
        $this->criadoEm       =         $dados['criado_em']       ?? '';
        $this->fornecedorNome =         $dados['fornecedor_nome'] ?? '';
    }

    public function precoFormatado(): string {
        return 'R$ ' . number_format($this->preco, 2, ',', '.');
    }

    /** Objeto de domínio Estoque (associação Produto 1 -- 0..1 Estoque). */
    public function getEstoque(): Estoque {
        return new Estoque($this->estoque, $this->preco);
    }
}
