<?php

class ItemPedido {
    public int    $id;
    public int    $pedidoId;
    public int    $produtoId;
    public int    $quantidade;
    public float  $precoUnit;
    public float  $subtotal;

    public string $produtoNome;
    public string $produtoFoto;

    public function __construct(array $dados) {
        $this->id          = (int)   $dados['id'];
        $this->pedidoId    = (int)   $dados['pedido_id'];
        $this->produtoId   = (int)   $dados['produto_id'];
        $this->quantidade  = (int)   $dados['quantidade'];
        $this->precoUnit   = (float) $dados['preco_unit'];
        $this->subtotal    = (float) ($dados['subtotal'] ?? $this->quantidade * $this->precoUnit);
        $this->produtoNome =         $dados['produto_nome'] ?? '';
        $this->produtoFoto =         $dados['produto_foto'] ?? '';
    }

    public function subtotalFormatado(): string {
        return 'R$ ' . number_format($this->subtotal, 2, ',', '.');
    }
}
