<?php
require_once __DIR__ . '/Usuario.php';

class Cliente extends Usuario {
    public function cartaoCredito(): string {
        return $this->cartaocredito;
    }
}
