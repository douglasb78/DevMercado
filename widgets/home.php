<?php
require_once __DIR__ . '/../dao/ProdutoDAO.php';
$dao       = new ProdutoDAO();
$destaques = $dao->listarTodos(8);
?>
<link rel="stylesheet" href="/widgets/home.css">
<link rel="stylesheet" href="../index.css">

<div id="home-page">
    <!-- Banner -->
    <div class="home-banner">
        <h2>Bem-vindo ao DevMercado</h2>
        <p>Os melhores produtos.</p>
        <a href="/navegar-produtos">Ver todos os produtos</a>
    </div>

    <!-- Produtos -->
    <?php if (!empty($destaques)): ?>
    <div class="home-section-title">Produtos:</div>
    <div class="home-grid">
        <?php foreach ($destaques as $p): ?>
        <a class="home-produto-card" href="/produto?id=<?= $p->id ?>">
            <img src="<?= htmlspecialchars($p->fotoUrl ?: 'https://placehold.co/200x160?text=Produto') ?>"
                 alt="<?= htmlspecialchars($p->nome) ?>">
            <h3><?= htmlspecialchars($p->nome) ?></h3>
            <div class="preco"><?= $p->precoFormatado() ?></div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
