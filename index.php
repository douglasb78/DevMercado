<?php
session_start();

require_once __DIR__ . '/include_all.php';

$url = $_GET['url'] ?? '';
$url = rtrim($url, '/');
$url = $url === '' ? 'home' : $url;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login')                  (new AuthController())->login();
    if ($action === 'registrar')              (new AuthController())->registrar();
    if ($action === 'atualizar_perfil')       (new AuthController())->atualizarPerfil();
    if ($action === 'carrinho_adicionar')     (new CarrinhoController())->adicionar();
    if ($action === 'carrinho_atualizar')     (new CarrinhoController())->atualizar();
    if ($action === 'carrinho_remover')       (new CarrinhoController())->remover();
    if ($action === 'pedido_finalizar')       (new PedidoController())->finalizar();
    if ($action === 'pedido_atualizar_status')(new PedidoController())->atualizarStatus();
    if ($action === 'produto_cadastrar')      (new ProdutoController())->cadastrar();
    if ($action === 'produto_atualizar')      (new ProdutoController())->atualizar();
    if ($action === 'produto_atualizar_estoque') (new ProdutoController())->atualizarEstoque();
    if ($action === 'produto_excluir')        (new ProdutoController())->excluir();
}

if ($url === 'sair') {
    (new AuthController())->logout();
}

$rotasLogin    = ['carrinho-de-compras', 'acompanhar-compras', 'perfil'];
$rotasSupplier = ['gerenciar-loja'];

if (in_array($url, $rotasLogin) && empty($_SESSION['usuario_id'])) {
    header('Location: /entrar'); exit;
}
if (in_array($url, $rotasSupplier) && empty($_SESSION['usuario_supplier'])) {
    header('Location: /'); exit;
}

$rotas = [
    'home'                => '/widgets/home.php',
    'acompanhar-compras'  => '/widgets/track_orders_page.php',
    'entrar'              => '/widgets/login_form.php',
    'carrinho-de-compras' => '/widgets/shopping_cart_form.php',
    'registrar'           => '/widgets/register_form.php',
    'navegar-produtos'    => '/widgets/products_page.php',
    'gerenciar-loja'      => '/widgets/manage_store.php',
    'resultados-busca'    => '/widgets/search_results_form.php',
    'produto'             => '/widgets/product_page.php',
    'perfil'              => '/widgets/profile.php',
    'loja-fornecedor'     => '/widgets/supplier_store.php',
];

$arquivo = $rotas[$url] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevMercado</title>
    <link rel="stylesheet" href="/widgets/reset.css">
    <link rel="stylesheet" href="/widgets/index.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/widgets/navbar_widget.php'; ?>
<main id="website-content">
<?php
    if ($arquivo && file_exists(__DIR__ . $arquivo)) {
        include __DIR__ . $arquivo;
    } else {
        echo "<h2 style='text-align:center;padding:50px;color:#fff;'>Página não encontrada</h2>";
    }
?>
</main>
<footer></footer>
</body>
</html>
