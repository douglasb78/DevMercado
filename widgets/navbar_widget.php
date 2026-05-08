<link rel="stylesheet" href="/widgets/navbar_widget.css">
<nav class="navbar">
    <a href="/" style="text-decoration:none;"><h1>DevMercado</h1></a>

    <div class="nav-links">
        <a href="/navegar-produtos">Produtos</a>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Buscar produto..."
                   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                   onkeydown="if(event.key==='Enter') executarBusca()">
            <button onclick="executarBusca()">Buscar</button>
        </div>

        <?php if (!empty($_SESSION['usuario_id'])): ?>
            <a href="/carrinho-de-compras">Carrinho</a>
            <a href="/acompanhar-compras">Acompanhar Compras</a>

            <?php if (!empty($_SESSION['usuario_supplier'])): ?>
                <a href="/gerenciar-loja" id="my_store">Minha Loja</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="spacer"></div>

    <div class="nav-links">
        <?php if (!empty($_SESSION['usuario_id'])): ?>
            <a href="/perfil" style="color:#fff;white-space:nowrap;">
                <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
            </a>
            <a href="/sair">Sair</a>
        <?php else: ?>
            <a href="/registrar">Criar conta</a>
            <a href="/entrar">Entrar</a>
        <?php endif; ?>
    </div>
</nav>

<script>
function executarBusca() {
    const q = document.getElementById('search-input')?.value?.trim() || '';
    if (!q) return;
    window.location.href = '/resultados-busca?q=' + encodeURIComponent(q);
}
</script>
