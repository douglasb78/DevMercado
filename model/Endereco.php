<?php

class Endereco {
    public string $rua;
    public string $numero;
    public string $complemento;
    public string $bairro;
    public string $cep;
    public string $cidade;
    public string $estado;

    public function __construct(array $dados = []) {
        $this->rua         = $dados['endereco_logradouro']  ?? $dados['rua']         ?? '';
        $this->numero      = $dados['endereco_numero']      ?? $dados['numero']      ?? '';
        $this->complemento = $dados['endereco_complemento'] ?? $dados['complemento'] ?? '';
        $this->bairro      = $dados['endereco_bairro']      ?? $dados['bairro']      ?? '';
        $this->cep         = $dados['cep']                  ?? '';
        $this->cidade      = $dados['endereco_cidade']      ?? $dados['cidade']      ?? '';
        $this->estado      = $dados['endereco_uf']          ?? $dados['estado']      ?? '';
    }

    public function preenchido(): bool {
        return trim($this->rua) !== '';
    }

    public function formatado(): string {
        if (!$this->preenchido()) {
            return '';
        }
        $out = $this->rua;
        if ($this->numero !== '')      $out .= ', ' . $this->numero;
        if ($this->complemento !== '') $out .= ' - ' . $this->complemento;
        if ($this->bairro !== '')      $out .= ' - ' . $this->bairro;
        if ($this->cidade !== '')      $out .= ', ' . $this->cidade;
        if ($this->estado !== '')      $out .= '/' . $this->estado;
        if ($this->cep !== '')         $out .= ' - CEP ' . $this->cep;
        return $out;
    }

    public function __toString(): string {
        return $this->formatado();
    }
}
