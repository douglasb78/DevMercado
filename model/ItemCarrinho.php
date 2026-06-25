<?php

class ItemCarrinho {
    public int    $id;
    public int    $usuarioId;
    public int    $produtoId;
    public int    $quantidade;
    public string $adicionadoEm;

    public string $produtoNome;
    public float  $produtoPreco;
    public string $produtoFoto;
    public string $fornecedorNome;
    public int    $estoqueDisponivel;

    public function __construct(array $dados) {
        $this->id                = (int)   $dados['id'];
        $this->usuarioId         = (int)   $dados['usuario_id'];
        $this->produtoId         = (int)   $dados['produto_id'];
        $this->quantidade        = (int)   $dados['quantidade'];
        $this->adicionadoEm      =         $dados['adicionado_em']     ?? '';
        $this->produtoNome       =         $dados['produto_nome']       ?? '';
        $this->produtoPreco      = (float) ($dados['produto_preco']     ?? 0);
        $this->produtoFoto       =         $dados['produto_foto']       ?? '';
        $this->fornecedorNome    =         $dados['fornecedor_nome']    ?? '';
        $this->estoqueDisponivel = (int)   ($dados['estoque_disponivel'] ?? 0);
    }

    public function subtotal(): float {
        return $this->quantidade * $this->produtoPreco;
    }

    public function subtotalFormatado(): string {
        return 'R$ ' . number_format($this->subtotal(), 2, ',', '.');
    }

}
