<?php
session_start();
require_once __DIR__ . '/dao/ProdutoDAO.php';

$dao     = new ProdutoDAO();
$id      = (int) ($_GET['id'] ?? 0);
$produto = $id ? $dao->buscarPorId($id) : null;

ob_start();

if (!$produto): ?>
<link rel="stylesheet" href="/css/pages/product.css">
<div class="produto-nao-encontrado">
  <h2>Produto não encontrado.</h2>
</div>

<?php
$website_content = ob_get_clean();
include __DIR__ . '/template/index_template.php';
return;
endif;

$carrinhoCookie = json_decode($_COOKIE['devmercado_carrinho'] ?? '{}', true);
$quantidadeAtualCarrinho = 0;
if (is_array($carrinhoCookie)) {
    $quantidadeAtualCarrinho = (int) ($carrinhoCookie[$produto->id] ?? 0);
}
$quantidadeInicial = max(1, min($produto->estoque, $quantidadeAtualCarrinho ?: 1));
$estoque = $produto->getEstoque();
?>

<link rel="stylesheet" href="/css/pages/product.css">
<div id="product-page">

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
      Vendido por: <a href="/view_store_page.php?id=<?= $produto->fornecedorId ?>" class="vendedor link-limpo">
        <strong><?= htmlspecialchars($produto->fornecedorNome) ?></strong>
      </a>
      <div id="stock-info" class="<?= $estoque->disponivel() ? 'estoque-ok' : 'estoque-esgotado' ?>">
        <?= $estoque->disponivel()
            ? "✓ Em estoque ({$estoque->quantidade} disponíveis)"
            : "✗ Produto indisponível" ?>
      </div>
    </div>

    <div class="product-buy-box">
      <div class="preco"><?= $produto->precoFormatado() ?></div>

      <?php if ($estoque->disponivel()): ?>
        <div class="quantidade-label">Quantidade:</div>
        <div class="quantidade">
          <button type="button" onclick="alterarQuantidade(-1)">–</button>
          <span id="qtd"><?= $quantidadeInicial ?></span>
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
        <button class="btn-comprar" disabled>
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

<div id="msg-carrinho" class="toast"></div>

<script>
const estoqueMax = <?= $produto->estoque ?>;
const produtoId = <?= $produto->id ?>;
const produtoPreco = <?= json_encode($produto->preco) ?>;
const produtoNome = <?= json_encode($produto->nome) ?>;
const produtoFoto = <?= json_encode($produto->fotoUrl ?: 'https://placehold.co/420x420?text=Sem+Foto') ?>;

document.addEventListener('DOMContentLoaded', () => {
  if (window.cartWidget && typeof window.cartWidget.showAddTotal === 'function') {
    window.cartWidget.showAddTotal(parseInt(document.getElementById('qtd').textContent), produtoPreco);
  }
});

function alterarQuantidade(valor) {
  const span = document.getElementById('qtd');
  let qtd = parseInt(span.textContent);
  const novaQtd = Math.max(1, Math.min(estoqueMax, qtd + valor));
  if (novaQtd === qtd) {
    if (valor > 0 && qtd >= estoqueMax) mostrarMensagem('Não há mais unidades em estoque.', '#c00');
    return;
  }

  const buttons = document.querySelectorAll('#product-page .product-buy-box .quantidade button');
  buttons.forEach(b => b.disabled = true);

  fetch('/shopping_cart_page.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=carrinho_atualizar&produto_id=' + produtoId + '&quantidade=' + novaQtd
  })
  .then(r => r.json())
  .then(data => {
    if (data.erro) {
      mostrarMensagem(data.erro, '#c00');
      return;
    }

    span.textContent = novaQtd;
    if (data.estoque !== undefined) {
      const restante = Math.max(0, data.estoque - novaQtd);
      const el = document.getElementById('stock-info');
      if (data.estoque <= 0) {
        el.textContent = '✗ Produto indisponível';
        el.className = 'estoque-esgotado';
      } else {
        el.textContent = '✓ Em estoque (' + restante + ' disponíveis)';
        el.className = restante > 0 ? 'estoque-ok' : 'estoque-esgotado';
      }
    }

    if (window.cartWidget && typeof window.cartWidget.showAddTotal === 'function') {
      window.cartWidget.showAddTotal(novaQtd, produtoPreco);
    }
  })
  .catch(() => mostrarMensagem('Erro ao atualizar quantidade.', '#c00'))
  .finally(() => buttons.forEach(b => b.disabled = false));
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
    body: `action=carrinho_atualizar&produto_id=${produtoId}&quantidade=${qtd}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.erro) {
      mostrarMensagem(data.erro, '#c00');
    } else {
      mostrarMensagem('✓ ' + data.mensagem);
      if (window.cartWidget && typeof window.cartWidget.addOrUpdateItem === 'function') {
        window.cartWidget.addOrUpdateItem({
          produtoId: produtoId,
          produtoNome: produtoNome,
          produtoPreco: produtoPreco,
          produtoFoto: produtoFoto,
          quantidade: qtd,
          quantidadeFinal: true
        });
      }
    }
  })
  .catch(() => mostrarMensagem('Erro ao adicionar ao carrinho.', '#c00'))
  .finally(() => {
    btn.disabled = false;
    btn.textContent = 'Adicionar ao Carrinho';
    if (window.cartWidget && typeof window.cartWidget.hideAddTotal === 'function') {
      window.cartWidget.hideAddTotal();
    }
  });
}

function comprarAgora(produtoId) {
  const qtd = parseInt(document.getElementById('qtd').textContent);
  fetch('/shopping_cart_page.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=carrinho_atualizar&produto_id=${produtoId}&quantidade=${qtd}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.erro) {
      mostrarMensagem(data.erro, '#c00');
    } else {
      if (window.cartWidget && typeof window.cartWidget.addOrUpdateItem === 'function') {
        window.cartWidget.addOrUpdateItem({
          produtoId: produtoId,
          produtoNome: produtoNome,
          produtoPreco: produtoPreco,
          produtoFoto: produtoFoto,
          quantidade: qtd,
          quantidadeFinal: true
        });
      }
      window.location.href = '/shopping_cart_page.php';
    }
  });
}
</script>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
