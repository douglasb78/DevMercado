<!-- Navbar !-->
<link rel="stylesheet" href="/css/components/navbar.css?v=5">
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
    <a href="index.php" class="link-limpo"><h1>DevMercado</h1></a>

    <div class="nav-links">
        <a href="listings_page.php">Produtos</a>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Buscar produto..." autocomplete="off"
                   value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                   onkeydown="if(event.key==='Enter') executarBusca()">
            <button onclick="executarBusca()">Buscar</button>
            <div id="search-suggestions" class="search-suggestions" role="listbox"></div>
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
            <a href="profile_page.php">
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

(function(){
  const input = document.getElementById('search-input');
  const box = document.getElementById('search-suggestions');
  if (!input || !box) return;

  let timer = null;
  let activeIndex = -1;

  function esc(s){
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }
  function fechar(){ box.style.display = 'none'; activeIndex = -1; }

  function render(produtos){
    activeIndex = -1;
    if (!produtos.length){
      box.innerHTML = '<div class="ss-empty">Nenhum produto encontrado</div>';
      box.style.display = 'block';
      return;
    }
    box.innerHTML = produtos.map(function(p){
      const foto = p.foto || 'https://placehold.co/40x40?text=Prod';
      const semEstoque = (Number(p.estoque) <= 0) ? '<span class="ss-stock">sem estoque</span>' : '';
      return '<a class="ss-item" role="option" href="/product_page.php?id=' + Number(p.id) + '">' +
               '<img src="' + esc(foto) + '" alt="">' +
               '<span class="ss-info"><span class="ss-nome">' + esc(p.nome) + '</span>' +
               '<span class="ss-cat">' + esc(p.categoria || '') + '</span></span>' +
               '<span class="ss-preco">' + esc(p.preco_formatado) + semEstoque + '</span>' +
             '</a>';
    }).join('');
    box.style.display = 'block';
  }

  async function buscar(q){
    try {
      const resp = await fetch('/api/produtos_busca.php?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
      const data = await resp.json();
      if (input.value.trim() !== q) return; // descarta resposta obsoleta
      render(data.produtos || []);
    } catch (e) { fechar(); }
  }

  input.addEventListener('input', function(){
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 2) { fechar(); return; }   // mínimo de 2 letras
    timer = setTimeout(function(){ buscar(q); }, 250);
  });

  input.addEventListener('focus', function(){
    if (input.value.trim().length >= 2 && box.children.length) box.style.display = 'block';
  });

  // Navegação por teclado (setas, Enter, Esc)
  input.addEventListener('keydown', function(e){
    const itens = box.querySelectorAll('.ss-item');
    if (e.key === 'Escape') { fechar(); return; }
    if (box.style.display !== 'block' || !itens.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex = (activeIndex + 1) % itens.length; }
    else if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex = (activeIndex - 1 + itens.length) % itens.length; }
    else if (e.key === 'Enter') { if (activeIndex >= 0) { e.preventDefault(); window.location.href = itens[activeIndex].href; } return; }
    else return;
    itens.forEach(function(el, i){ el.classList.toggle('ss-active', i === activeIndex); });
    itens[activeIndex].scrollIntoView({ block: 'nearest' });
  });

  document.addEventListener('click', function(e){
    if (!e.target.closest('.search-bar')) fechar();
  });
})();
</script>
