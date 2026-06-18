<?php
session_start();
require_once __DIR__ . '/include_all.php';

$usuarioDao = new UsuarioDAO();
if (!empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_admin'])) {
    $usuarioLogado = $usuarioDao->buscarPorId((int) $_SESSION['usuario_id']);
    $_SESSION['usuario_admin'] = $usuarioLogado?->isAdmin ?? false;
}

if (empty($_SESSION['usuario_admin'])) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

$aba = $_GET['aba'] ?? 'clientes';
$abasPermitidas = ['clientes', 'fornecedores', 'produtos', 'pedidos'];
if (!in_array($aba, $abasPermitidas, true)) {
    $aba = 'clientes';
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 12;
$offset = ($pagina - 1) * $porPagina;

$produtoDao = new ProdutoDAO();
$clientes = $fornecedores = $produtos = [];
$total = 0;

$pedidoDao = new PedidoDAO();
$pedidos = [];

if ($aba === 'clientes') {
    $clientes = $usuarioDao->listarPorTipo(false, $porPagina, $offset);
    $total = $usuarioDao->contarPorTipo(false);
} elseif ($aba === 'fornecedores') {
    $fornecedores = $usuarioDao->listarPorTipo(true, $porPagina, $offset);
    $total = $usuarioDao->contarPorTipo(true);
} elseif ($aba === 'produtos') {
  $produtos = $produtoDao->listarTodos($porPagina, $offset);
  $total = $produtoDao->contar();
} elseif ($aba === 'pedidos') {
  $pedidos = $pedidoDao->listarTodosPaginado($porPagina, $offset);
  $total = $pedidoDao->contarTodos();
} else {
  $produtos = $produtoDao->listarTodos($porPagina, $offset);
  $total = $produtoDao->contar();
}

$totalPaginas = max(1, (int) ceil($total / $porPagina));

function admin_link(string $aba, int $pagina): string {
    return '/admin_page.php?aba=' . urlencode($aba) . '&pagina=' . $pagina;
}

ob_start();
?>
<link rel="stylesheet" href="/css/admin_page.css">

<div id="admin-page">
  <div class="admin-header">
    <h2>Admin</h2>
    <span><?= $total ?> registro<?= $total === 1 ? '' : 's' ?></span>
  </div>

  <div class="admin-tabs">
    <a class="<?= $aba === 'clientes' ? 'active' : '' ?>" href="<?= admin_link('clientes', 1) ?>">Clientes</a>
    <a class="<?= $aba === 'fornecedores' ? 'active' : '' ?>" href="<?= admin_link('fornecedores', 1) ?>">Fornecedores</a>
    <a class="<?= $aba === 'produtos' ? 'active' : '' ?>" href="<?= admin_link('produtos', 1) ?>">Produtos</a>
    <a class="<?= $aba === 'pedidos' ? 'active' : '' ?>" href="<?= admin_link('pedidos', 1) ?>">Pedidos</a>
  </div>
  <?php if ($aba === 'clientes' || $aba === 'fornecedores'): ?>
    <?php $usuarios = $aba === 'clientes' ? $clientes : $fornecedores; ?>
    <table class="admin-master-table admin-users-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>E-mail</th>
          <th>Telefone</th>
          <th>Endereço</th>
          <th>Criado em</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $usuario): ?>
          <tr class="admin-master-row">
            <td data-label="ID"><?= $usuario->id ?></td>
            <td data-label="Nome"><?= htmlspecialchars($usuario->nome) ?></td>
            <td data-label="E-mail"><?= htmlspecialchars($usuario->email) ?></td>
            <td data-label="Telefone"><?= htmlspecialchars($usuario->telefone) ?></td>
            <td data-label="Endereco"><?= htmlspecialchars($usuario->endereco) ?></td>
            <td data-label="Criado em"><?= htmlspecialchars($usuario->criadoEm) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  <?php elseif ($aba === 'produtos'): ?>
    <table class="admin-master-table admin-products-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Foto</th>
          <th>Produto</th>
          <th>Fornecedor</th>
          <th>Categoria</th>
          <th>Preço</th>
          <th>Estoque</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($produtos as $produto): ?>
          <?php $fotoRaw = $produto->fotoUrl ?: 'https://placehold.co/80x80?text=Prod'; $foto = htmlspecialchars($fotoRaw); ?>
          <tr class="admin-master-row">
            <td data-label="ID"><?= $produto->id ?></td>
            <td data-label="Foto" onclick="event.stopPropagation()" class="td-fotos">
              <button type="button" class="thumb-button" onclick='abrirImagem(<?= json_encode($fotoRaw) ?>, <?= json_encode($produto->nome) ?>)'>
                <img src="<?= $foto ?>" alt="<?= htmlspecialchars($produto->nome) ?>">
              </button>
            </td>
            <td data-label="Produto"><?= htmlspecialchars($produto->nome) ?></td>
            <td data-label="Fornecedor"><?= htmlspecialchars($produto->fornecedorNome) ?></td>
            <td data-label="Categoria"><?= htmlspecialchars($produto->categoria) ?></td>
            <td data-label="Preco"><?= $produto->precoFormatado() ?></td>
            <td data-label="Estoque"><?= $produto->estoque ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  <?php elseif ($aba === 'pedidos'): ?>
    <table class="admin-master-table admin-orders-table">
      <thead>
        <tr>
          <th>Pedido</th>
          <th>Comprador</th>
          <th>Fornecedores</th>
          <th>Fotos</th>
          <th>Produtos</th>
          <th>Status</th>
          <th>Estimativa</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pedidos as $pedido): ?>
          <tr class="admin-master-row admin-expand-row" onclick="toggleDetalhe('pedido-<?= $pedido->id ?>')">
            <td data-label="Pedido">
              <div class="pedido-numero">#<?= $pedido->id ?></div>
              <div class="pedido-data"><?= $pedido->dataCompraFormatada() ?></div>
            </td>
            <td><?= htmlspecialchars($pedido->compradorNome ?: '—') ?></td>
            <td><?= htmlspecialchars($pedido->fornecedores ?: '—') ?></td>
            <td data-label="Fotos">
              <?php
                $items = [];
                foreach ($pedido->itens as $it) {
                  $items[] = ['src' => $it->produtoFoto ?: 'https://placehold.co/80x80?text=Prod', 'alt' => $it->produtoNome, 'title' => $it->produtoNome];
                }
                include __DIR__ . '/template/thumb_carroussel.php';
              ?>
            </td>
            <td><?= htmlspecialchars(implode(', ', array_map(fn($i)=>$i->produtoNome, array_slice($pedido->itens,0,3))) . (count($pedido->itens)>3 ? ' +' . (count($pedido->itens)-3) : '')) ?></td>
            <td><?= htmlspecialchars($pedido->statusLabel()) ?></td>
            <td><?= $pedido->dataEstimadaFormatada() ?></td>
            <td><?= $pedido->totalFormatado() ?></td>
          </tr>
          <tr id="pedido-<?= $pedido->id ?>" class="admin-detail-row">
            <td colspan="8">
              <div class="pedido-itens-detalhe">
                <?php foreach ($pedido->itens as $item): ?>
                  <?php
                    $fotoItemRaw = $item->produtoFoto ?: 'https://placehold.co/80x80?text=Prod';
                    $precoItem = 'R$ ' . number_format($item->precoUnit, 2, ',', '.');
                    $produtoUrl = '/product_page.php?id=' . $item->produtoId;
                    $fornecedorUrl = '/view_store_page.php?id=' . $item->fornecedorId;
                  ?>
                  <div class="pedido-item-row">
                    <button type="button" class="thumb-button pedido-item-foto" onclick='abrirImagem(<?= json_encode($fotoItemRaw) ?>, <?= json_encode($item->produtoNome) ?>)'>
                      <img src="<?= htmlspecialchars($fotoItemRaw) ?>" alt="<?= htmlspecialchars($item->produtoNome) ?>">
                    </button>
                    <div class="pedido-item-info">
                      <a class="pedido-item-nome" href="<?= $produtoUrl ?>"><?= htmlspecialchars($item->produtoNome) ?></a>
                      <span>Quantidade: <?= $item->quantidade ?></span>
                      <span>Preço: <?= $precoItem ?></span>
                      <a href="<?= $fornecedorUrl ?>">Fornecedor: <?= htmlspecialchars($item->fornecedorNome ?: 'Fornecedor') ?></a>
                    </div>
                    <a class="pedido-produto-link" href="<?= $produtoUrl ?>">Ver produto</a>
                  </div>
                <?php endforeach; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($totalPaginas > 1): ?>
    <div class="paginacao">
      <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
        <a class="<?= $i === $pagina ? 'active' : '' ?>" href="<?= admin_link($aba, $i) ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<div class="image-modal" id="image-modal" onclick="fecharImagem()">
  <div class="image-modal-content" onclick="event.stopPropagation()">
    <button type="button" onclick="fecharImagem()">Fechar</button>
    <img id="modal-img" src="" alt="">
    <span id="modal-title"></span>
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
