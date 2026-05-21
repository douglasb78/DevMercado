<?php
session_start();
require_once __DIR__ . '/include_all.php';

// Processar ações do carrinho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'carrinho_adicionar') {
        $controller = new CarrinhoController();
        $controller->adicionar();
    } elseif ($action === 'carrinho_atualizar') {
        $controller = new CarrinhoController();
        $controller->atualizar();
    } elseif ($action === 'carrinho_remover') {
        $controller = new CarrinhoController();
        $controller->remover();
    } elseif ($action === 'pedido_finalizar') {
        $controller = new PedidoController();
        $controller->finalizar();
    }
}

$dao   = new CarrinhoDAO();
$uid   = (int) ($_SESSION['usuario_id'] ?? 0);
$itens = $uid ? $dao->listarPorUsuario($uid) : [];
$total = array_sum(array_map(fn($i) => $i->subtotal(), $itens));
ob_start();
?>
<link rel="stylesheet" href="/css/shopping_cart_page.css">
<div id="shopping_cart">
  <h2>Carrinho de Compras</h2>

  <?php if (empty($itens)): ?>
    <p style="text-align:center;padding:40px;color:#666;border:1px solid #ddd;">
      Seu carrinho está vazio.
      <br><br>
      <a href="/listings_page.php" style="color:#0066cc;">Ver produtos</a>
    </p>
  <?php else: ?>

    <?php foreach ($itens as $item): ?>
      <div class="carrinho-item" id="item-<?= $item->produtoId ?>">
        <img src="<?= htmlspecialchars($item->produtoFoto ?: 'https://placehold.co/80x80?text=Prod') ?>"
             alt="<?= htmlspecialchars($item->produtoNome) ?>"
             class="produto-img">

        <div class="info-produto">
          <strong><?= htmlspecialchars($item->produtoNome) ?></strong><br>
          <small>Vendido por: <?= htmlspecialchars($item->fornecedorNome) ?></small>
        </div>

        <div class="quantidade">
          <button type="button" onclick="alterarQtd(<?= $item->produtoId ?>, -1)">–</button>
          <span id="qtd-<?= $item->produtoId ?>" style="width:30px;text-align:center;">
            <?= $item->quantidade ?>
          </span>
          <button type="button" onclick="alterarQtd(<?= $item->produtoId ?>, 1)">+</button>
        </div>

        <div class="preco" id="sub-<?= $item->produtoId ?>">
          <?= $item->subtotalFormatado() ?>
        </div>

        <div class="acoes">
          <button class="remover" type="button"
                  onclick="removerItem(<?= $item->produtoId ?>)">Remover</button>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Resumo -->
    <div class="resumo">
      <h2>Resumo do Pedido</h2>
      <p>Itens: <strong id="total-itens"><?= count($itens) ?></strong></p>
      <p>Frete: <strong>R$ 0,00</strong> <small>(Frete grátis)</small></p>
      <div class="total" id="total-geral">
        Total: <?= 'R$ ' . number_format($total, 2, ',', '.') ?>
      </div>
      <button class="btn-finalizar" type="button" onclick="finalizarCompra()">
        Finalizar Compra
      </button>
    </div>

  <?php endif; ?>

  <div id="msg-cart" style="display:none;margin-top:12px;padding:12px;
       background:#d4edda;border:1px solid #c3e6cb;color:#155724;font-weight:600;"></div>
</div>

<script>
const precos = {
<?php foreach ($itens as $item): ?>
  <?= $item->produtoId ?>: <?= $item->produtoPreco ?>,
<?php endforeach; ?>
};

const qtds = {
<?php foreach ($itens as $item): ?>
  <?= $item->produtoId ?>: <?= $item->quantidade ?>,
<?php endforeach; ?>
};

function mostrarMsg(msg, ok = true) {
  const el = document.getElementById('msg-cart');
  el.textContent = msg;
  el.style.background = ok ? '#d4edda' : '#f8d7da';
  el.style.color       = ok ? '#155724' : '#721c24';
  el.style.borderColor = ok ? '#c3e6cb' : '#f5c6cb';
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 3000);
}

function atualizarTotal() {
  let total = 0;
  Object.keys(qtds).forEach(pid => {
    if (qtds[pid] > 0) total += qtds[pid] * precos[pid];
  });
  document.getElementById('total-geral').textContent =
    'Total: R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
}

function alterarQtd(prodId, delta) {
  const novaQtd = Math.max(1, (qtds[prodId] || 1) + delta);
  qtds[prodId] = novaQtd;
  document.getElementById('qtd-' + prodId).textContent = novaQtd;

  const sub = novaQtd * precos[prodId];
  document.getElementById('sub-' + prodId).textContent =
    'R$ ' + sub.toLocaleString('pt-BR', {minimumFractionDigits: 2});

  atualizarTotal();

  fetch('/shopping_cart_page.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=carrinho_atualizar&produto_id=${prodId}&quantidade=${novaQtd}`
  });
}

function removerItem(prodId) {
  fetch('/shopping_cart_page.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=carrinho_remover&produto_id=${prodId}`
  })
  .then(r => r.json())
  .then(() => {
    document.getElementById('item-' + prodId)?.remove();
    delete qtds[prodId];
    delete precos[prodId];
    atualizarTotal();
    const total = Object.keys(qtds).length;
    document.getElementById('total-itens').textContent = total;
    if (total === 0) location.reload();
  });
}

function finalizarCompra() {
  const btn = document.querySelector('.btn-finalizar');
  btn.disabled = true;
  btn.textContent = 'Processando...';

  fetch('/shopping_cart_page.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=pedido_finalizar'
  })
  .then(r => r.json())
  .then(data => {
    if (data.erro) {
      mostrarMsg(data.erro, false);
      btn.disabled = false;
      btn.textContent = 'Finalizar Compra';
    } else {
      mostrarMsg('✓ ' + data.mensagem + ' Redirecionando...');
      setTimeout(() => {
        window.location.href = '/track_orders_page.php?pedido_id=' + data.pedido_id;
      }, 2000);
    }
  })
  .catch(() => {
    mostrarMsg('Erro ao finalizar. Tente novamente.', false);
    btn.disabled = false;
    btn.textContent = 'Finalizar Compra';
  });
}
</script>
<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
