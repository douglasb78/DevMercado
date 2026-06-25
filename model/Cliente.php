<?php
require_once __DIR__ . '/Usuario.php';

/**
 * Cliente (conforme o diagrama de classes do trabalho): nome, telefone, email,
 * cartaoCredito, possui um Endereco e realiza Pedidos (0..*).
 *
 * É um Usuario sem a marca de fornecedor (is_supplier = false). O sistema mantém
 * um cadastro único de pessoa em `usuarios`; o DAO instancia esta subclasse para
 * os usuários que atuam como compradores.
 */
class Cliente extends Usuario {

    /** Cartão de crédito do cliente (mascarado/armazenado em Usuario::$cartaocredito). */
    public function cartaoCredito(): string {
        return $this->cartaocredito;
    }
}
