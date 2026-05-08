<?php
require_once __DIR__ . '/../dao/PedidoDAO.php';

$dao     = new PedidoDAO();
$uid     = (int) ($_SESSION['usuario_id'] ?? 0);
$pedidos = $uid ? $dao->listarPorComprador($uid) : [];
?>
<link rel="stylesheet" href="/widgets/track_orders_page.css">
<link rel="stylesheet" href="../index.css">
<div id="track_orders">
  <h2>Acompanhar Compras</h2>

  <?php if (empty($pedidos)): ?>
    <p style="text-align:center;padding:40px;color:#666;border:1px solid #ddd;">
      Você ainda não fez nenhum pedido.
      <br><br>
      <a href="/navegar-produtos" style="color:#0066cc;">Ver produtos</a>
    </p>
  <?php else: ?>

    <?php foreach ($pedidos as $pedido): ?>
      <div style="margin-bottom:24px;">
        <div style="background:#f5f5f5;border:1px solid #ddd;padding:10px 15px;
                    font-size:0.88rem;color:#444;margin-bottom:4px;">
          <strong>Pedido #<?= $pedido->id ?></strong> &nbsp;·&nbsp;
          <?= $pedido->dataCompraFormatada() ?> &nbsp;·&nbsp;
          Total: <?= $pedido->totalFormatado() ?>
        </div>

        <?php foreach ($pedido->itens as $item): ?>
          <div class="pedido-card">
            <img src="<?= htmlspecialchars($item->produtoFoto ?: 'https://placehold.co/70x70?text=Prod') ?>"
                 alt="<?= htmlspecialchars($item->produtoNome) ?>"
                 class="produto-img">

            <div class="info-principal">
              <strong><?= htmlspecialchars($item->produtoNome) ?></strong>
              <div class="detalhes">
                <?= $item->quantidade ?> unidade<?= $item->quantidade > 1 ? 's' : '' ?> &nbsp;·&nbsp;
                <?= $item->subtotalFormatado() ?>
                <br>
                <span class="data-compra">Comprado em <?= $pedido->dataCompraFormatada() ?></span>
              </div>
            </div>

            <div>
              <div class="status <?= $pedido->status ?>">
                <?= $pedido->statusLabel() ?>
              </div>
              <?php if ($pedido->dataEstimada): ?>
                <div class="detalhes" style="margin-top:8px;font-size:0.85rem;">
                  Estimativa: <strong><?= $pedido->dataEstimadaFormatada() ?></strong>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>
