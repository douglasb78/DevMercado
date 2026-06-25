<?php

/**
 * Estoque de um Produto (conforme o diagrama de classes do trabalho).
 *
 * Relação Produto 1 -- 0..1 Estoque. No banco a quantidade e o preço estão na
 * própria tabela `produtos`; esta classe expõe esses dados como o objeto de
 * domínio Estoque, usado para decidir disponibilidade e formatar o preço.
 */
class Estoque {
    public int   $quantidade;
    public float $preco;

    public function __construct(int $quantidade, float $preco) {
        $this->quantidade = $quantidade;
        $this->preco      = $preco;
    }

    /** Item pode ser vendido? (estoque ZERO => indisponível) */
    public function disponivel(): bool {
        return $this->quantidade > 0;
    }

    public function precoFormatado(): string {
        return 'R$ ' . number_format($this->preco, 2, ',', '.');
    }
}
