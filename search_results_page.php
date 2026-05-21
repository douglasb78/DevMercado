<?php
session_start();
require_once __DIR__ . '/dao/ProdutoDAO.php';
require_once __DIR__ . '/dao/UsuarioDAO.php';

$produtoDAO = new ProdutoDAO();
$usuarioDAO = new UsuarioDAO();
$termo = trim($_GET['q'] ?? $_POST['q'] ?? '');

$produtos = [];
$fornecedores = [];
$totalProdutos = 0;
$totalFornecedores = 0;

if ($termo) {
    $produtos = $produtoDAO->buscar($termo);
    $fornecedores = $usuarioDAO->buscarFornecedores($termo);
    $totalProdutos = count($produtos);
    $totalFornecedores = count($fornecedores);
}
ob_start();
?>
<link rel="stylesheet" href="/css/search_results_page.css">
<div id="search_results">
  <div class="main-content">

    <div class="header">
      <h1>Resultados para "<?= htmlspecialchars($termo) ?>"</h1>
      <p><?= $totalProdutos + $totalFornecedores ?> resultado<?= $totalProdutos + $totalFornecedores !== 1 ? 's' : '' ?> encontrado<?= $totalProdutos + $totalFornecedores !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($produtos) && empty($fornecedores)): ?>
      <p style="text-align:center;padding:40px;color:#666;background:#fff;border:1px solid #ddd;">
        <?= $termo
            ? 'Nenhum resultado encontrado para "' . htmlspecialchars($termo) . '".'
            : 'Digite um termo para buscar produtos ou fornecedores.' ?>
      </p>
    <?php else: ?>

      <?php if (!empty($fornecedores)): ?>
        <div class="secao-fornecedores">
          <h2>Fornecedores (<?= $totalFornecedores ?>)</h2>
          <div class="fornecedores-grid">
            <?php foreach ($fornecedores as $f): ?>
              <a href="/view_store_page.php?id=<?= $f->id ?>" class="fornecedor-card" style="text-decoration:none;color:inherit;">
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
            <div class="produto-card">
              <img src="<?= htmlspecialchars($p->fotoUrl ?: 'https://placehold.co/130x130?text=Sem+Foto') ?>"
                   alt="<?= htmlspecialchars($p->nome) ?>"
                   class="produto-img">

              <div class="produto-info">
                <h3><?= htmlspecialchars($p->nome) ?></h3>
                <div class="vendedor">
                  Vendido por: 
                  <a href="/view_store_page.php?id=<?= $p->fornecedorId ?>" style="color:#667eea;text-decoration:none;">
                    <strong><?= htmlspecialchars($p->fornecedorNome) ?></strong>
                  </a>
                </div>
                <?php if ($p->descricao): ?>
                  <p style="font-size:0.88rem;color:#555;margin-top:6px;line-height:1.5;">
                    <?= htmlspecialchars(mb_substr($p->descricao, 0, 120)) ?>...
                  </p>
                <?php endif; ?>
              </div>

              <div class="acoes">
                <div class="preco"><?= $p->precoFormatado() ?></div>
                <a href="/product_page.php?id=<?= $p->id ?>" style="text-decoration:none;">
                  <button class="btn-detalhes">Ver Detalhes</button>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
