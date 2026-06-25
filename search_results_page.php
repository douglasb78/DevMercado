<?php
session_start();
require_once __DIR__ . '/dao/ProdutoDAO.php';
require_once __DIR__ . '/dao/UsuarioDAO.php';

$produtoDAO = new ProdutoDAO();
$usuarioDAO = new UsuarioDAO();
$termo = trim($_GET['q'] ?? $_POST['q'] ?? '');

$pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 12;
$offset    = ($pagina - 1) * $porPagina;

$produtos = [];
$fornecedores = [];
$totalProdutos = 0;
$totalFornecedores = 0;
$totalPaginas = 1;

if ($termo) {
    $produtos = $produtoDAO->buscar($termo, $porPagina, $offset);
    $fornecedores = $usuarioDAO->buscarFornecedores($termo);
    $totalProdutos = $produtoDAO->contarBusca($termo);
    $totalFornecedores = count($fornecedores);
    $totalPaginas = max(1, (int) ceil($totalProdutos / $porPagina));
}
ob_start();
?>
<link rel="stylesheet" href="/css/pages/search_results.css">
<div id="search-results">
  <div class="main-content">

    <div class="header">
      <h1>Resultados para "<?= htmlspecialchars($termo) ?>"</h1>
      <p><?= $totalProdutos + $totalFornecedores ?> resultado<?= $totalProdutos + $totalFornecedores !== 1 ? 's' : '' ?> encontrado<?= $totalProdutos + $totalFornecedores !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($produtos) && empty($fornecedores)): ?>
      <p class="estado-vazio estado-vazio--card">
        <?= $termo
            ? 'Nenhum resultado encontrado para "' . htmlspecialchars($termo) . '".'
            : 'Digite um termo para buscar produtos ou fornecedores.' ?>
      </p>
    <?php else: ?>

      <?php if (!empty($fornecedores) && $pagina === 1): ?>
        <div class="secao-fornecedores">
          <h2>Fornecedores (<?= $totalFornecedores ?>)</h2>
          <div class="fornecedores-grid">
            <?php foreach ($fornecedores as $f): ?>
              <a href="/view_store_page.php?id=<?= $f->id ?>" class="fornecedor-card link-limpo">
                <div class="fornecedor-avatar">
                  <span><?= strtoupper(substr($f->nome, 0, 1)) ?></span>
                </div>
                <div class="fornecedor-info">
                  <h3><?= htmlspecialchars($f->nome) ?></h3>
                  <p><?= htmlspecialchars($f->email) ?></p>
                  <?php if ($f->telefone): ?>
                    <p class="telefone">Telefone: <?= htmlspecialchars($f->telefone) ?></p>
                  <?php endif; ?>
                </div>
                <div class="fornecedor-arrow">→</div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($produtos)): ?>
        <div class="secao-produtos">
          <h2>Produtos (<?= $totalProdutos ?>)</h2>
          <?php foreach ($produtos as $p): ?>
            <?php $semEstoque = !$p->getEstoque()->disponivel(); ?>
            <div class="produto-card<?= $semEstoque ? ' indisponivel' : '' ?>">
              <?php if ($semEstoque): ?><span class="badge-indisponivel">Indisponível</span><?php endif; ?>
              <img src="<?= htmlspecialchars($p->fotoUrl ?: 'https://placehold.co/130x130?text=Sem+Foto') ?>"
                   alt="<?= htmlspecialchars($p->nome) ?>"
                   class="produto-img">

              <div class="produto-info">
                <h3><?= htmlspecialchars($p->nome) ?></h3>
                <?php if ($semEstoque): ?>
                  <div class="estoque-indisponivel">Produto sem estoque no momento</div>
                <?php endif; ?>
                <div class="vendedor">
                  Vendido por: 
                  <a href="/view_store_page.php?id=<?= $p->fornecedorId ?>" class="link-fornecedor">
                    <strong><?= htmlspecialchars($p->fornecedorNome) ?></strong>
                  </a>
                </div>
                <?php if ($p->descricao): ?>
                  <p class="descricao-curta">
                    <?= htmlspecialchars(mb_substr($p->descricao, 0, 120)) ?>...
                  </p>
                <?php endif; ?>
              </div>

              <div class="acoes">
                <div class="preco"><?= $p->precoFormatado() ?></div>
                <a href="/product_page.php?id=<?= $p->id ?>" class="link-limpo">
                  <button class="btn-detalhes">Ver Detalhes</button>
                </a>
              </div>
            </div>
          <?php endforeach; ?>

          <?php if ($totalPaginas > 1): ?>
            <div class="paginacao">
              <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <a class="<?= $i === $pagina ? 'active' : '' ?>"
                   href="/search_results_page.php?q=<?= urlencode($termo) ?>&pagina=<?= $i ?>"><?= $i ?></a>
              <?php endfor; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
