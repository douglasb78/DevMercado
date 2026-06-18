<!-- Navbar !-->
<link rel="stylesheet" href="/template/css/navbar_widget.css">
<?php
if (!empty($_SESSION['usuario_id'])) {
    require_once __DIR__ . '/../dao/UsuarioDAO.php';
    $usuarioDao = new UsuarioDAO();
    $freshUser = $usuarioDao->buscarPorId((int) $_SESSION['usuario_id']);
    $isSupplier = $freshUser?->isSupplier ?? (!empty($_SESSION['usuario_supplier']));
} else {
    $isSupplier = false;
}
?>
<nav class="navbar">
    <a href="index.php" style="text-decoration:none;"><h1>DevMercado</h1></a>

    <div class="nav-links">
        <a href="listings_page.php">Produtos</a>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Buscar produto..."
                   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                   onkeydown="if(event.key==='Enter') executarBusca()">
            <button onclick="executarBusca()">Buscar</button>
        </div>

        <a href="shopping_cart_page.php">Carrinho</a>

        <?php if (!empty($_SESSION['usuario_id'])): ?>
            <a href="track_orders_page.php">Acompanhar Compras</a>

            <?php if (!empty($_SESSION['usuario_admin'])): ?>
                <a href="admin_page.php">Admin</a>
            <?php endif; ?>

            <?php if (!empty($isSupplier)): ?>
                <a href="manage_page.php" id="my_store">Minha Loja</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="nav-links">
        <?php if (!empty($_SESSION['usuario_id'])): ?>
            <a href="profile_page.php" style="color:#fff;white-space:nowrap;">
                <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
            </a>
            <a href="index.php?logout=true">Sair</a>
        <?php else: ?>
            <a href="register_page.php">Criar conta</a>
            <a href="login_page.php">Entrar</a>
        <?php endif; ?>
    </div>
</nav>
<!-- Navbar - Script de Pesquisar!-->
<script>
function executarBusca() {
    const q = document.getElementById('search-input')?.value?.trim() || '';
    if (!q) return;
    window.location.href = 'search_results_page.php?q=' + encodeURIComponent(q);
}
</script>
