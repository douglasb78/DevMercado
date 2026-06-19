<?php
session_start();
require_once __DIR__ . '/include_all.php';

$dao = new PedidoDAO();
$uid = (int) ($_SESSION['usuario_id'] ?? 0);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 10;
$offset = ($pagina - 1) * $porPagina;

$total = $uid ? $dao->contarPorComprador($uid) : 0;
$pedidos = $uid ? $dao->listarPorCompradorPaginado($uid, $porPagina, $offset) : [];
$totalPaginas = max(1, (int) ceil($total / $porPagina));

function pedidoThumbUrl(?string $url): string {
    return htmlspecialchars($url ?: 'https://placehold.co/80x80?text=Prod');
}

function pedidoProdutosResumo(array $itens): string {
    $nomes = array_map(fn($item) => $item->produtoNome, $itens);
    $primeiros = array_slice($nomes, 0, 2);
    $extra = count($nomes) > 2 ? ' +' . (count($nomes) - 2) : '';
    return htmlspecialchars(implode(', ', $primeiros) . $extra);
}

ob_start();
?>
<link rel="stylesheet" href="/css/track_orders_page.css">

<div id="track_orders" class="compact-page">
  <div class="compact-header">
    <h2>Acompanhar Compras</h2>
    <span><?= $total ?> pedido<?= $total === 1 ? '' : 's' ?></span>
  </div>

  <?php if (empty($pedidos)): ?>
    <p class="empty-state">
      Você ainda não fez nenhum pedido.
      <br><br>
      <a href="/listings_page.php">Ver produtos</a>
    </p>
  <?php else: ?>
    <table class="master-table">
      <thead>
        <tr>
          <th>Pedido</th>
          <th>Produtos</th>
          <th>Fotos</th>
          <th>Status</th>
          <th>Estimativa</th>
          <th>Total</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pedidos as $pedido): ?>
          <tr class="master-row" onclick="toggleDetalhe('pedido-<?= $pedido->id ?>')">
            <td data-label="Pedido">
              #<?= $pedido->id ?> - 
              <?= $pedido->dataCompraFormatada() ?>
            </td>
            <td data-label="Produtos"><?= pedidoProdutosResumo($pedido->itens) ?></td>
            <td data-label="Fotos">
              <?php
                $items = [];
                foreach ($pedido->itens as $item) {
                  $items[] = [
                    'src' => $item->produtoFoto ?: 'https://placehold.co/80x80?text=Prod',
                    'alt' => $item->produtoNome,
                    'title' => $item->produtoNome,
                  ];
                }
                include __DIR__ . '/template/thumb_carroussel.php';
              ?>
            </td>
            <td data-label="Status"><span class="status <?= htmlspecialchars($pedido->status) ?>"><?= $pedido->statusLabel() ?></span></td>
            <td data-label="Estimativa"><?= $pedido->dataEstimadaFormatada() ?></td>
            <td data-label="Total"><?= $pedido->totalFormatado() ?></td>
            <td data-label="Ação"><button type="button" class="detail-toggle">Detalhes</button></td>
          </tr>
          <tr id="pedido-<?= $pedido->id ?>" class="detail-row">
            <td colspan="7">
              <table class="detail-table">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Unidades</th>
                    <th>Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pedido->itens as $item): ?>
                    <tr>
                      <td>
                        <span class="detail-product">
                          <img src="<?= pedidoThumbUrl($item->produtoFoto) ?>" alt="<?= htmlspecialchars($item->produtoNome) ?>"
                               onclick='abrirImagem(<?= json_encode($item->produtoFoto ?: 'https://placehold.co/80x80?text=Prod') ?>, <?= json_encode($item->produtoNome) ?>)'>
                          <?= htmlspecialchars($item->produtoNome) ?>
                        </span>
                      </td>
                      <td><?= $item->quantidade ?></td>
                      <td><?= 'R$ ' . number_format($item->precoUnit, 2, ',', '.') ?></td>
                      <td><?= $item->subtotalFormatado() ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($totalPaginas > 1): ?>
      <div class="paginacao">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
          <a class="<?= $i === $pagina ? 'active' : '' ?>" href="/track_orders_page.php?pagina=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="image-modal" id="image-modal" onclick="fecharImagem()">
  <div class="image-modal-content" onclick="event.stopPropagation()">
    <button type="button" onclick="fecharImagem()">Fechar</button>
    <img id="modal-img" src="" alt="">
    <strong id="modal-title"></strong>
  </div>
</div>

<script>
function toggleDetalhe(id) {
  document.getElementById(id)?.classList.toggle('visible');
}

function abrirImagem(src, titulo) {
  document.getElementById('modal-img').src = src;
  document.getElementById('modal-title').textContent = titulo;
  document.getElementById('image-modal').classList.add('visible');
}

function fecharImagem() {
  document.getElementById('image-modal').classList.remove('visible');
}
</script>
<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
