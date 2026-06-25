<?php

class Estoque {
    public int   $quantidade;
    public float $preco;

    public function __construct(int $quantidade, float $preco) {
        $this->quantidade = $quantidade;
        $this->preco      = $preco;
    }

    public function disponivel(): bool {
        return $this->quantidade > 0;
    }

    public function precoFormatado(): string {
        return 'R$ ' . number_format($this->preco, 2, ',', '.');
    }
}
