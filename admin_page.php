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
    <table class="admin-master-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>E-mail</th>
          <th>Telefone</th>
          <th>Endereço</th>
          <th>Criado em</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $usuario): ?>
          <tr class="admin-master-row" onclick="toggleDetalhe('usuario-<?= $usuario->id ?>')">
            <td><?= $usuario->id ?></td>
            <td><?= htmlspecialchars($usuario->nome) ?></td>
            <td><?= htmlspecialchars($usuario->email) ?></td>
            <td><?= htmlspecialchars($usuario->telefone) ?></td>
            <td><?= htmlspecialchars($usuario->endereco) ?></td>
            <td><?= htmlspecialchars($usuario->criadoEm) ?></td>
            <td><button type="button">Detalhes</button></td>
          </tr>
          <tr id="usuario-<?= $usuario->id ?>" class="admin-detail-row">
            <td colspan="7">
              <div class="detail-grid">
                <span><strong>Tipo:</strong> <?= $usuario->isSupplier ? 'Fornecedor' : 'Cliente' ?></span>
                <span><strong>Admin:</strong> <?= $usuario->isAdmin ? 'Sim' : 'Não' ?></span>
                <span><strong>Cartão:</strong> <?= htmlspecialchars($usuario->cartaocredito) ?></span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  <?php elseif ($aba === 'produtos'): ?>
    <table class="admin-master-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Foto</th>
          <th>Produto</th>
          <th>Fornecedor</th>
          <th>Categoria</th>
          <th>Preço</th>
          <th>Estoque</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($produtos as $produto): ?>
          <?php $fotoRaw = $produto->fotoUrl ?: 'https://placehold.co/80x80?text=Prod'; $foto = htmlspecialchars($fotoRaw); ?>
          <tr class="admin-master-row" onclick="toggleDetalhe('produto-<?= $produto->id ?>')">
            <td><?= $produto->id ?></td>
            <td onclick="event.stopPropagation()" class="td-fotos">
              <button type="button" class="thumb-button" onclick='abrirImagem(<?= json_encode($fotoRaw) ?>, <?= json_encode($produto->nome) ?>)'>
                <img src="<?= $foto ?>" alt="<?= htmlspecialchars($produto->nome) ?>">
              </button>
            </td>
            <td><?= htmlspecialchars($produto->nome) ?></td>
            <td><?= htmlspecialchars($produto->fornecedorNome) ?></td>
            <td><?= htmlspecialchars($produto->categoria) ?></td>
            <td><?= $produto->precoFormatado() ?></td>
            <td><?= $produto->estoque ?></td>
            <td><button type="button">Detalhes</button></td>
          </tr>
          <tr id="produto-<?= $produto->id ?>" class="admin-detail-row">
            <td colspan="8">
              <div class="detail-grid">
                <span><strong>Fornecedor ID:</strong> <?= $produto->fornecedorId ?></span>
                <span><strong>Criado em:</strong> <?= htmlspecialchars($produto->criadoEm) ?></span>
                <span><strong>Foto URL:</strong> <?= htmlspecialchars($produto->fotoUrl) ?></span>
              </div>
              <p><?= htmlspecialchars($produto->descricao ?: 'Sem descrição.') ?></p>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  <?php elseif ($aba === 'pedidos'): ?>
    <table class="admin-master-table">
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
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pedidos as $pedido): ?>
          <tr class="admin-master-row" onclick="toggleDetalhe('pedido-<?= $pedido->id ?>')">
            <td>
              <strong>#<?= $pedido->id ?></strong>
              <small><?= $pedido->dataCompraFormatada() ?></small>
            </td>
            <td><?= htmlspecialchars($pedido->compradorNome ?: '—') ?></td>
            <td><?= htmlspecialchars($pedido->fornecedores ?: '—') ?></td>
            <td>
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
            <td><button type="button">Detalhes</button></td>
          </tr>
          <tr id="pedido-<?= $pedido->id ?>" class="admin-detail-row">
            <td colspan="9">
              <div class="detail-grid">
                <span><strong>Comprador:</strong> <?= htmlspecialchars($pedido->compradorNome) ?></span>
                <span><strong>Fornecedores:</strong> <?= htmlspecialchars($pedido->fornecedores) ?></span>
                <span><strong>Criado em:</strong> <?= htmlspecialchars($pedido->criadoEm) ?></span>
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
