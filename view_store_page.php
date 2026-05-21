<?php
session_start();
require_once __DIR__ . '/dao/ProdutoDAO.php';
require_once __DIR__ . '/dao/UsuarioDAO.php';

$fornecedorId = (int) ($_GET['id'] ?? 0);

if (!$fornecedorId) {
    echo "<div class='supplier-container'><p style='color:red;'>Fornecedor não especificado.</p></div>";
    exit;
}

$usuarioDAO = new UsuarioDAO();
$fornecedor = $usuarioDAO->buscarPorId($fornecedorId);

if (!$fornecedor || !$fornecedor->isSupplier) {
    echo "<div class='supplier-container'><p style='color:red;'>Fornecedor não encontrado.</p></div>";
    exit;
}

$produtoDAO = new ProdutoDAO();
$produtos = $produtoDAO->listarPorFornecedor($fornecedorId);
ob_start();
?>
<link rel="stylesheet" href="/css/view_store_page.css">

<div class="supplier-container">
    <div class="supplier-header">
        <div class="supplier-info">
            <h1><?= htmlspecialchars($fornecedor->nome) ?></h1>
            <p class="supplier-email">E-mail: <?= htmlspecialchars($fornecedor->email) ?></p>
            <?php if ($fornecedor->telefone): ?>
                <p class="supplier-phone">Telefone: <?= htmlspecialchars($fornecedor->telefone) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="products-section">
        <h2>Produtos (<?= count($produtos) ?>)</h2>

        <?php if (empty($produtos)): ?>
            <div class="empty-state">
                <p>Este fornecedor não possui produtos cadastrados no momento.</p>
            </div>
        <?php else: ?>
            <div class="produtos-grid">
                <?php foreach ($produtos as $p): ?>
                    <a class="produto-card" href="/product_page.php?id=<?= $p->id ?>" style="text-decoration:none;color:inherit;">
                        <img src="<?= htmlspecialchars($p->fotoUrl ?: 'https://placehold.co/220x180?text=Sem+Foto') ?>"
                             alt="<?= htmlspecialchars($p->nome) ?>">
                        <h3><?= htmlspecialchars($p->nome) ?></h3>
                        <p class="categoria"><?= htmlspecialchars($p->categoria ?? 'Sem categoria') ?></p>
                        <div class="preco"><?= $p->precoFormatado() ?></div>
                        <?php if ($p->estoque > 0): ?>
                            <div class="estoque-disponivel">Em estoque</div>
                        <?php else: ?>
                            <div class="estoque-indisponivel">Sem estoque</div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="back-link">
        <a href="/listings_page.php">← Voltar para produtos</a>
    </div>
</div>
<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>