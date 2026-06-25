<?php
session_start();
require_once __DIR__ . '/dao/ProdutoDAO.php';

$dao        = new ProdutoDAO();
$categorias = $dao->listarCategorias();

$categoriaAtiva = $_GET['categoria'] ?? null;
$pagina         = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina      = 12;
$offset         = ($pagina - 1) * $porPagina;

if ($categoriaAtiva) {
    $produtos = $dao->listarPorCategoria($categoriaAtiva, $porPagina, $offset);
    $total    = $dao->contar($categoriaAtiva);
} else {
    $produtos = $dao->listarTodos($porPagina, $offset);
    $total    = $dao->contar();
}

$totalPaginas = (int) ceil($total / $porPagina);

ob_start();
?>
<link rel="stylesheet" href="/css/pages/listings.css">
<div id="products-page">

  <div class="sidebar">
    <h2>Categorias</h2>
    <a href="/listings_page.php"
       class="categoria <?= !$categoriaAtiva ? 'active' : '' ?>">
      Todas as Categorias
    </a>
    <?php foreach ($categorias as $cat): ?>
      <a href="/listings_page.php?categoria=<?= urlencode($cat['categoria']) ?>"
         class="categoria <?= $categoriaAtiva === $cat['categoria'] ? 'active' : '' ?>">
        <?= htmlspecialchars($cat['categoria']) ?>
        <small>(<?= $cat['total'] ?>)</small>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="main-content">
    <div class="header">
      <h1><?= $categoriaAtiva ? htmlspecialchars($categoriaAtiva) : 'Todos os Produtos' ?></h1>
      <p><?= $total ?> produto<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></p>
    </div>

    <div class="produtos-grid">
      <?php if (empty($produtos)): ?>
        <p class="estado-vazio">
          Nenhum produto encontrado.
        </p>
      <?php else: ?>
        <?php foreach ($produtos as $p): ?>
          <?php $semEstoque = !$p->getEstoque()->disponivel(); ?>
          <a class="produto-card link-limpo<?= $semEstoque ? ' indisponivel' : '' ?>" href="/product_page.php?id=<?= $p->id ?>">
            <?php if ($semEstoque): ?><span class="badge-indisponivel">Indisponível</span><?php endif; ?>
            <img src="<?= htmlspecialchars($p->fotoUrl ?: 'https://placehold.co/220x180?text=Sem+Foto') ?>"
                 alt="<?= htmlspecialchars($p->nome) ?>">
            <h3><?= htmlspecialchars($p->nome) ?></h3>
            <div class="preco"><?= $p->precoFormatado() ?></div>
            <div class="<?= $semEstoque ? 'estoque-indisponivel' : 'estoque-disponivel' ?>">
              <?= $semEstoque ? 'Indisponível' : 'Em estoque' ?>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <div class="paginacao">
      <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
        <a href="/listings_page.php?pagina=<?= $i ?><?= $categoriaAtiva ? '&categoria=' . urlencode($categoriaAtiva) : '' ?>"
           class="<?= $i === $pagina ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
