<?php
session_start();
require_once __DIR__ . '/include_all.php';

if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    $auth = new AuthController();
    $auth->logout();
}

$dao       = new ProdutoDAO();
$destaques = $dao->listarDisponiveis(28);

ob_start();
?>

<link rel="stylesheet" href="/css/pages/home.css">

<div id="home-page">
    <div class="home-banner">
        <h2>Bem-vindo ao DevMercado</h2>
        <p>Os melhores produtos.</p>
        <a href="/listings_page.php">Ver todos os produtos</a>
    </div>

    <?php if (!empty($destaques)): ?>
        <div class="home-section-title">Produtos:</div>
        <div class="home-grid">
            <?php foreach ($destaques as $p): ?>
                <a class="home-produto-card" href="/product_page.php?id=<?= $p->id ?>">
                    <img src="<?= htmlspecialchars($p->fotoUrl ?: 'https://placehold.co/200x160?text=Produto') ?>"
                         alt="<?= htmlspecialchars($p->nome) ?>">
                    <h3><?= htmlspecialchars($p->nome) ?></h3>
                    <div class="preco"><?= $p->precoFormatado() ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
