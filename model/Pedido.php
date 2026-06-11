<?php

class Pedido {
    public int    $id;
    public int    $compradorId;
    public string $status;
    public string $dataEstimada;
    public float  $total;
    public string $criadoEm;
    public string $compradorNome;
    public string $compradorEndereco;

    public array $itens = [];

    public function __construct(array $dados) {
        $this->id           = (int)   $dados['id'];
        $this->compradorId  = (int)   $dados['comprador_id'];
        $this->status       =         $dados['status']         ?? 'preparacao';
        $this->dataEstimada =         $dados['data_estimada']  ?? '';
        $this->total        = (float) $dados['total'];
        $this->criadoEm     =         $dados['criado_em']      ?? '';
        $this->compradorNome =        $dados['comprador_nome'] ?? '';
        $this->compradorEndereco =    $dados['comprador_endereco'] ?? '';
    }

    public function statusLabel(): string {
        return match($this->status) {
            'preparacao' => 'Em preparação',
            'transito'   => 'Em trânsito',
            'saiu'       => 'Saiu para entrega',
            'entregue'   => 'Entregue',
            default      => 'Status invÃ¡lido',
        };
    }

    public function totalFormatado(): string {
        return 'R$ ' . number_format($this->total, 2, ',', '.');
    }

    public function dataEstimadaFormatada(): string {
        if (!$this->dataEstimada) return '—';
        $dt = DateTime::createFromFormat('Y-m-d', $this->dataEstimada);
        return $dt ? $dt->format('d/m/Y') : $this->dataEstimada;
    }

    public function dataCompraFormatada(): string {
        if (!$this->criadoEm) return '—';
        $dt = new DateTime($this->criadoEm);
        return $dt->format('d/m/Y');
    }
}
