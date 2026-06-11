<?php
session_start();
require_once __DIR__ . '/dao/ProdutoDAO.php';

$dao     = new ProdutoDAO();
$id      = (int) ($_GET['id'] ?? 0);
$produto = $id ? $dao->buscarPorId($id) : null;

ob_start();

if (!$produto): ?>
<link rel="stylesheet" href="/css/product_page.css">
<div style="text-align:center;padding:60px;color:#fff;">
  <h2>Produto não encontrado.</h2>
</div>

<?php
$website_content = ob_get_clean();
include __DIR__ . '/template/index_template.php';
return;
endif;
?>

<link rel="stylesheet" href="/css/product_page.css">
<div id="product_page">

  <div class="product-gallery">
    <img
      src="<?= htmlspecialchars($produto->fotoUrl ?: 'https://placehold.co/420x420?text=Sem+Foto') ?>"
      alt="<?= htmlspecialchars($produto->nome) ?>"
      class="foto-principal"
      id="foto-principal"
    >
  </div>

  <div class="product-details">

    <div class="product-header">
      <h1><?= htmlspecialchars($produto->nome) ?> #<?= $produto->id ?></h1> 
      Vendido por: <a href="/view_store_page.php?id=<?= $produto->fornecedorId ?>" class="vendedor" style="text-decoration:none;color:inherit;">
        <strong><?= htmlspecialchars($produto->fornecedorNome) ?></strong>
      </a>
      <div style="margin-top:6px;font-size:0.85rem;color:<?= $produto->estoque > 0 ? '#00a650' : '#c00' ?>;">
        <?= $produto->estoque > 0
            ? "✓ Em estoque ({$produto->estoque} disponíveis)"
            : "✗ Produto indisponível" ?>
      </div>
    </div>

    <div class="product-buy-box">
      <div class="preco"><?= $produto->precoFormatado() ?></div>
      <div class="frete">Frete grátis</div>

      <?php if ($produto->estoque > 0): ?>
        <div class="quantidade-label">Quantidade:</div>
        <div class="quantidade">
          <button type="button" onclick="alterarQuantidade(-1)">–</button>
          <span id="qtd">1</span>
          <button type="button" onclick="alterarQuantidade(1)">+</button>
        </div>

        <button class="btn-adicionar" id="btn-adicionar" type="button"
                onclick="adicionarCarrinho(<?= $produto->id ?>)">
          Adicionar ao Carrinho
        </button>
        <button class="btn-comprar" type="button"
                onclick="comprarAgora(<?= $produto->id ?>)">
          Comprar Agora
        </button>
      <?php else: ?>
        <button class="btn-comprar" disabled style="background:#aaa;cursor:not-allowed;">
          Produto Indisponível
        </button>
      <?php endif; ?>
    </div>

    <div class="product-description">
      <h2>Descrição do Produto</h2>
      <p><?= nl2br(htmlspecialchars($produto->descricao ?: 'Sem descrição disponível.')) ?></p>
    </div>

  </div>
</div>

<div id="msg-carrinho" style="display:none;position:fixed;bottom:24px;right:24px;
     background:#00a650;color:#fff;padding:14px 24px;border-radius:4px;
     font-weight:600;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,0.2);">
</div>

<script>
const estoqueMax = <?= $produto->estoque ?>;

function alterarQuantidade(valor) {
  const span = document.getElementById('qtd');
  let qtd = parseInt(span.textContent);
  if (valor > 0 && qtd >= estoqueMax) {
    mostrarMensagem('Não há mais unidades em estoque.', '#c00');
    return;
  }
  qtd = Math.max(1, Math.min(estoqueMax, qtd + valor));
  span.textContent = qtd;
}

function mostrarMensagem(msg, cor = '#00a650') {
  const el = document.getElementById('msg-carrinho');
  el.textContent = msg;
  el.style.background = cor;
  el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 3000);
}

function adicionarCarrinho(produtoId) {
  const qtd = parseInt(document.getElementById('qtd').textContent);
  const btn = document.getElementById('btn-adicionar');
  btn.disabled = true;
  btn.textContent = 'Adicionando...';

  fetch('/shopping_cart_page.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=carrinho_adicionar&produto_id=${produtoId}&quantidade=${qtd}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.erro) {
      mostrarMensagem(data.erro, '#c00');
    } else {
      mostrarMensagem('✓ ' + data.mensagem);
    }
  })
  .catch(() => mostrarMensagem('Erro ao adicionar ao carrinho.', '#c00'))
  .finally(() => {
    btn.disabled = false;
    btn.textContent = 'Adicionar ao Carrinho';
  });
}

function comprarAgora(produtoId) {
  const qtd = parseInt(document.getElementById('qtd').textContent);
  fetch('/shopping_cart_page.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=carrinho_adicionar&produto_id=${produtoId}&quantidade=${qtd}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.erro) {
      mostrarMensagem(data.erro, '#c00');
    } else {
      window.location.href = '/shopping_cart_page.php';
    }
  });
}
</script>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
