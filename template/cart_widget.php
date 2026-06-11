<?php
// Widget flutuante do carrinho - inclui dados iniciais do servidor
require_once __DIR__ . '/../include_all.php';

$cc = new CarrinhoController();
$itens = $cc->itens();

$cartItems = [];
$total = 0;
$qtyTotal = 0;
foreach ($itens as $it) {
    $cartItems[] = [
        'produtoId' => $it->produtoId,
        'produtoNome' => $it->produtoNome,
        'produtoPreco' => $it->produtoPreco,
        'produtoFoto' => $it->produtoFoto,
        'quantidade' => $it->quantidade,
        'subtotal' => $it->subtotal(),
    ];
    $total += $it->subtotal();
    $qtyTotal += $it->quantidade;
}

$cartJson = json_encode($cartItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$cartTotal = $total;
$cartQty = $qtyTotal;
?>

<style>
/* Minimal styles for floating cart widget */
#cart-widget { position: fixed; right: 24px; bottom: 24px; width: 300px; font-family: 'Poppins',sans-serif; z-index: 10000; }
#cart-widget .cw-toggle { background:#fff; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.12); padding:10px 12px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; }
#cart-widget .cw-toggle strong { font-size:0.95rem; }
#cart-widget .cw-count { margin-left:8px;color:#666;font-size:0.9rem }
#cart-widget .cw-total { font-weight:700;color:#00a650 }
#cart-widget .cw-panel { margin-top:8px; background:#fff; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.08); overflow:hidden; display:none; }
#cart-widget .cw-items { max-height:260px; overflow:auto; }
.cw-item { display:flex; gap:8px; padding:10px; align-items:center; border-bottom:1px solid #f0f0f0 }
.cw-item img { width:48px; height:48px; object-fit:cover; border-radius:6px }
.cw-item .meta { flex:1 }
.cw-item .meta .name { font-size:0.92rem; font-weight:600 }
.cw-item .meta .qty { font-size:0.85rem; color:#666 }
.cw-item .price { font-weight:700 }
.cw-actions { padding:10px; text-align:right }
.cw-actions a { background:#0066cc;color:#fff;padding:8px 12px;border-radius:6px;text-decoration:none }
#cart-widget .cw-extension { margin-top:8px; background:linear-gradient(90deg,#f8fdf9,#f3fff6); padding:10px 12px; border-radius:10px; display:none; box-shadow:0 6px 18px rgba(0,0,0,0.06); font-weight:600 }
</style>

<div id="cart-widget" aria-live="polite">
  <div class="cw-toggle" id="cart-widget-toggle" role="button" aria-expanded="false">
    <div>
      <strong>Carrinho</strong>
      <span class="cw-count" id="cart-widget-count"><?= $cartQty ?></span>
    </div>
    <div class="cw-total" id="cart-widget-total"><?= 'R$ ' . number_format($cartTotal, 2, ',', '.') ?></div>
  </div>

  <div class="cw-panel" id="cart-widget-panel">
    <div class="cw-items" id="cart-widget-items"></div>
    <div class="cw-actions"><a href="/shopping_cart_page.php">Ver Carrinho</a></div>
  </div>

  <div class="cw-extension" id="cart-widget-extension"></div>
</div>

<script>
  (function(){
    const initial = <?= $cartJson ?: '[]' ?>;
    let state = { items: initial };

    function formatCurrency(v){
      return 'R$ ' + Number(v).toLocaleString('pt-BR', {minimumFractionDigits:2});
    }

    const el = document.getElementById('cart-widget');
    const toggle = document.getElementById('cart-widget-toggle');
    const panel = document.getElementById('cart-widget-panel');
    const itemsEl = document.getElementById('cart-widget-items');
    const countEl = document.getElementById('cart-widget-count');
    const totalEl = document.getElementById('cart-widget-total');
    const extEl = document.getElementById('cart-widget-extension');

    function render(){
      const items = state.items || [];
      let qty = 0; let total = 0;
      itemsEl.innerHTML = '';
      if (items.length === 0) {
        itemsEl.innerHTML = '<div style="padding:14px;color:#666;text-align:center">Carrinho vazio</div>';
      } else {
        items.forEach(it => {
          qty += Number(it.quantidade || 0);
          total += Number(it.subtotal || (it.quantidade * it.produtoPreco || 0));

          const node = document.createElement('div');
          node.className = 'cw-item';
          node.innerHTML = `
            <img src="${escapeHtml(it.produtoFoto || 'https://placehold.co/70x70?text=Prod')}" alt="${escapeHtml(it.produtoNome)}">
            <div class="meta">
              <div class="name">${escapeHtml(it.produtoNome)}</div>
              <div class="qty">${it.quantidade} × ${formatCurrency(it.produtoPreco)}</div>
            </div>
            <div class="price">${formatCurrency(it.subtotal || (it.quantidade * it.produtoPreco))}</div>
          `;
          itemsEl.appendChild(node);
        });
      }

      countEl.textContent = qty;
      totalEl.textContent = formatCurrency(total);
    }

    function escapeHtml(s){
      if (!s) return '';
      return String(s).replace(/[&<>\"]/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
      });
    }

    toggle.addEventListener('click', () => {
      const opened = panel.style.display === 'block';
      panel.style.display = opened ? 'none' : 'block';
      toggle.setAttribute('aria-expanded', String(!opened));
    });

    window.cartWidget = {
      update(items){
        state.items = (items || []).map(i => ({
          produtoId: Number(i.produtoId),
          produtoNome: i.produtoNome || i.produto_name || '',
          produtoPreco: Number(i.produtoPreco || i.preco || 0),
          produtoFoto: i.produtoFoto || i.foto || '',
          quantidade: Number(i.quantidade || 0),
          subtotal: Number(i.subtotal || (i.quantidade * (i.produtoPreco || 0)))
        }));
        render();
      },
      addOrUpdateItem(item){
        const pid = Number(item.produtoId);
        const existing = state.items.find(i => Number(i.produtoId) === pid);
        if (existing){
          existing.quantidade = Number(existing.quantidade || 0) + Number(item.quantidade || 0);
          existing.produtoPreco = Number(item.produtoPreco || existing.produtoPreco || 0);
          existing.subtotal = existing.quantidade * existing.produtoPreco;
        } else {
          state.items.unshift({
            produtoId: pid,
            produtoNome: item.produtoNome || '',
            produtoPreco: Number(item.produtoPreco || 0),
            produtoFoto: item.produtoFoto || '',
            quantidade: Number(item.quantidade || 0),
            subtotal: Number(item.quantidade || 0) * Number(item.produtoPreco || 0)
          });
        }
        render();
      },
      removeItem(prodId){
        state.items = state.items.filter(i => Number(i.produtoId) !== Number(prodId));
        render();
      },
      showAddTotal(qtd, unitPrice){
        extEl.textContent = `Total a adicionar: ${formatCurrency(Number(qtd) * Number(unitPrice))}`;
        extEl.style.display = 'block';
      },
      hideAddTotal(){
        extEl.style.display = 'none';
      }
    };

    // Inicializar com dados do servidor
    window.cartWidget.update(initial);
  })();
</script>
