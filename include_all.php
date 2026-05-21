<?php
require_once __DIR__ . '/model/Usuario.php';
require_once __DIR__ . '/model/Produto.php';
require_once __DIR__ . '/model/Pedido.php';
require_once __DIR__ . '/model/ItemPedido.php';
require_once __DIR__ . '/model/ItemCarrinho.php';

require_once __DIR__ . '/dao/Database.php';
require_once __DIR__ . '/dao/UsuarioDAO.php';
require_once __DIR__ . '/dao/ProdutoDAO.php';
require_once __DIR__ . '/dao/CarrinhoDAO.php';
require_once __DIR__ . '/dao/PedidoDAO.php';

require_once __DIR__ . '/controller/AuthController.php';
require_once __DIR__ . '/controller/ProdutoController.php';
require_once __DIR__ . '/controller/CarrinhoController.php';
require_once __DIR__ . '/controller/PedidoController.php';
