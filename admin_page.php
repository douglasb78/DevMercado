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

$aba = $_GET['aba'] ?? 'usuarios';
// Map legacy tab names to the unified 'usuarios' tab
$map = ['clientes' => 'usuarios', 'fornecedores' => 'usuarios'];
if (isset($map[$aba])) {
  $aba = $map[$aba];
}
$abasPermitidas = ['usuarios', 'produtos', 'pedidos'];
if (!in_array($aba, $abasPermitidas, true)) {
  $aba = 'usuarios';
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 12;
$offset = ($pagina - 1) * $porPagina;

$produtoDao = new ProdutoDAO();
$clientes = $fornecedores = $produtos = [];
$total = 0;

$pedidoDao = new PedidoDAO();
$pedidos = [];

// Processar ações administrativas via POST (toggle_supplier também suportado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];

  // toggle_supplier mantém o comportamento antigo (redirect)
  if ($action === 'toggle_supplier') {
    if (empty($_SESSION['usuario_admin'])) {
      http_response_code(403);
      echo 'Acesso negado.';
      exit;
    }

    $targetId = (int) ($_POST['id'] ?? 0);
    $desired = isset($_POST['is_supplier']);
    $target = $usuarioDao->buscarPorId($targetId);
    if ($target) {
      $usuarioDao->atualizar(
        $targetId,
        $target->email,
        null,
        $target->telefone ?? null,
        $target->cartaocredito ?? null,
        $target->endereco ?? null,
        $target->nome ?? null,
        $desired
      );
    }

    header('Location: ' . admin_link('usuarios', $pagina));
    exit;
  }

  // As demais ações requerem que o usuário seja admin
  if (empty($_SESSION['usuario_admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Acesso negado.']);
    exit;
  }

  // Atualizar usuário (admin)
  if ($action === 'usuario_atualizar') {
    $uid = (int) ($_POST['usuario_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cartaocredito = trim($_POST['cartaocredito'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $isSupplier = isset($_POST['is_supplier']);
    $senha = $_POST['senha'] ?? '';
    $senhaConfirm = $_POST['senha_confirm'] ?? '';

    if (!$uid || !$email || !$nome) {
      http_response_code(400);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'ID, nome e e-mail são obrigatórios.']);
      exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      http_response_code(400);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'E-mail inválido.']);
      exit;
    }

    $existing = $usuarioDao->buscarPorEmail($email);
    if ($existing && $existing->id !== $uid) {
      http_response_code(400);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'Este e-mail já está cadastrado por outro usuário.']);
      exit;
    }

    $senhaHash = null;
    if ($senha !== '') {
      if (strlen($senha) < 6) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'A senha deve ter pelo menos 6 caracteres.']);
        exit;
      }
      if ($senha !== $senhaConfirm) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'As senhas não coincidem.']);
        exit;
      }
      $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
    }

    try {
      $usuarioAtualizado = $usuarioDao->atualizar(
        $uid,
        $email,
        $senhaHash,
        $telefone ?: null,
        $cartaocredito ?: null,
        $endereco ?: null,
        $nome ?: null,
        $isSupplier
      );
      header('Content-Type: application/json');
      echo json_encode(['mensagem' => 'Usuário atualizado com sucesso!']);
      exit;
    } catch (Throwable $e) {
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'Erro ao atualizar usuário.']);
      exit;
    }
  }

  // Atualizar produto (admin) - aceita upload de imagem via foto_arquivo
  if ($action === 'admin_produto_atualizar') {
    $produtoId = (int) ($_POST['produto_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
    $categoria = trim($_POST['categoria'] ?? '');
    $fotoUrl = trim($_POST['foto_url'] ?? '');

    if (!$produtoId || !$nome) {
      http_response_code(400);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'ID e nome do produto são obrigatórios.']);
      exit;
    }

    $produto = $produtoDao->buscarPorId($produtoId);
    if (!$produto) {
      http_response_code(404);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'Produto não encontrado.']);
      exit;
    }

    // tratar upload de imagem
    $fotoFinal = $fotoUrl ?: null;
    if (!empty($_FILES['foto_arquivo']) && ($_FILES['foto_arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['foto_arquivo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'Erro ao enviar imagem.']);
        exit;
      }
      $tmp = $_FILES['foto_arquivo']['tmp_name'];
      $info = @getimagesize($tmp);
      if (!$info) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'O arquivo enviado precisa ser uma imagem.']);
        exit;
      }
      $extensoes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
      ];
      $ext = $extensoes[$info[2]] ?? null;
      if (!$ext) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'Formato de imagem não permitido.']);
        exit;
      }
      $pasta = __DIR__ . '/upload_media';
      if (!is_dir($pasta)) mkdir($pasta, 0775, true);
      $nomeArquivo = 'produto_admin_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
      $destino = $pasta . '/' . $nomeArquivo;
      if (!move_uploaded_file($tmp, $destino)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'Não foi possível salvar a imagem.']);
        exit;
      }
      $fotoFinal = '/upload_media/' . $nomeArquivo;
    }

    try {
      $produtoDao->atualizar($produtoId, $nome, $descricao, $preco, $categoria, $fotoFinal ?? '');
      header('Content-Type: application/json');
      echo json_encode(['mensagem' => 'Produto atualizado com sucesso!']);
      exit;
    } catch (Throwable $e) {
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'Erro ao atualizar produto.']);
      exit;
    }
  }

  // Marcar produto como removido / restaurar (soft delete)
  if ($action === 'admin_produto_set_deleted') {
    $produtoId = (int) ($_POST['produto_id'] ?? 0);
    $isDeleted = !empty($_POST['is_deleted']);
    if (!$produtoId) {
      http_response_code(400);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'ID do produto inválido.']);
      exit;
    }
    try {
      $ok = $produtoDao->setDeleted($produtoId, $isDeleted);
      header('Content-Type: application/json');
      echo json_encode(['mensagem' => $ok ? 'Operação realizada.' : 'Nenhuma alteração.']);
      exit;
    } catch (Throwable $e) {
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'Erro ao atualizar produto.']);
      exit;
    }
  }

  // Atualizar quantidade de item em pedido (admin)
  if ($action === 'pedido_item_atualizar') {
    $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
    $produtoId = (int) ($_POST['produto_id'] ?? 0);
    $quant = (int) ($_POST['quantidade'] ?? 0);

    if (!$pedidoId || !$produtoId) {
      http_response_code(400);
      header('Content-Type: application/json');
      echo json_encode(['erro' => 'IDs inválidos.']);
      exit;
    }

    try {
      $novoTotal = $pedidoDao->atualizarItemQuantidade($pedidoId, $produtoId, $quant);
      header('Content-Type: application/json');
      echo json_encode(['mensagem' => 'Quantidade atualizada.', 'novo_total' => $novoTotal, 'quantidade' => $quant]);
      exit;
    } catch (Throwable $e) {
      http_response_code(400);
      header('Content-Type: application/json');
      echo json_encode(['erro' => $e->getMessage()]);
      exit;
    }
  }

}

if ($aba === 'usuarios') {
  $usuarios = $usuarioDao->listarTodos($porPagina, $offset);
  $total = $usuarioDao->contarTodos();
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
    <a class="<?= $aba === 'usuarios' ? 'active' : '' ?>" href="<?= admin_link('usuarios', 1) ?>">Usuários</a>
    <a class="<?= $aba === 'produtos' ? 'active' : '' ?>" href="<?= admin_link('produtos', 1) ?>">Produtos</a>
    <a class="<?= $aba === 'pedidos' ? 'active' : '' ?>" href="<?= admin_link('pedidos', 1) ?>">Pedidos</a>
  </div>
  <?php if ($aba === 'usuarios'): ?>
    <table class="admin-master-table admin-users-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>E-mail</th>
          <th>Telefone</th>
          <th>Endereço</th>
          <th>É fornecedor</th>
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
            <td data-label="É fornecedor">
              <form method="POST" action="/admin_page.php">
                <input type="hidden" name="action" value="toggle_supplier">
                <input type="hidden" name="id" value="<?= $usuario->id ?>">
                <input type="checkbox" name="is_supplier" <?= $usuario->isSupplier ? 'checked' : '' ?> onchange="this.form.submit()">
              </form>
            </td>
            <td data-label="Criado em">
              <?= htmlspecialchars($usuario->criadoEm) ?>
              <button type="button" onclick='abrirModalEditarUsuario(<?= $usuario->id ?>, <?= json_encode($usuario->nome) ?>, <?= json_encode($usuario->email) ?>, <?= json_encode($usuario->telefone) ?>, <?= json_encode($usuario->endereco) ?>, <?= json_encode($usuario->cartaocredito) ?>, <?= $usuario->isSupplier ? 'true' : 'false' ?>)' style="margin-left:8px;">Editar</button>
            </td>
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
          <th>Ações</th>
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
            <td data-label="Ações">
              <button type="button" onclick='abrirModalEditarProdutoAdmin(<?= $produto->id ?>, <?= json_encode($produto->nome) ?>, <?= json_encode($produto->descricao) ?>, <?= json_encode($produto->preco) ?>, <?= json_encode($produto->categoria) ?>, <?= json_encode($produto->fotoUrl ?: '') ?>, false)'>Editar</button>
              <button type="button" onclick='abrirModalEditarProdutoAdmin(<?= $produto->id ?>, <?= json_encode($produto->nome) ?>, <?= json_encode($produto->descricao) ?>, <?= json_encode($produto->preco) ?>, <?= json_encode($produto->categoria) ?>, <?= json_encode($produto->fotoUrl ?: '') ?>, true)'>Excluir</button>
            </td>
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
                      <button type="button" onclick='abrirModalEditarItemPedido(<?= $pedido->id ?>, <?= $item->produtoId ?>, <?= json_encode($item->produtoNome) ?>, <?= $item->quantidade ?>)' style="margin-left:8px;">Editar Qtd</button>
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

<!-- Modal Editar Usuário (admin) -->
<div id="modal-usuario-editar" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;justify-content:center;align-items:center;">
  <div style="background:white;padding:20px;border-radius:8px;max-width:500px;width:90%;">
    <h3 style="margin-top:0;">Editar Usuário</h3>
    <input type="hidden" id="admin-usuario-id">
    <div style="margin-bottom:8px;"><label>Nome</label><input id="admin-usuario-nome" style="width:100%"></div>
    <div style="margin-bottom:8px;"><label>E-mail</label><input id="admin-usuario-email" style="width:100%"></div>
    <div style="margin-bottom:8px;"><label>Telefone</label><input id="admin-usuario-telefone" style="width:100%"></div>
    <div style="margin-bottom:8px;"><label>Endereço</label><input id="admin-usuario-endereco" style="width:100%"></div>
    <div style="margin-bottom:8px;"><label>Cartão</label><input id="admin-usuario-cartao" style="width:100%"></div>
    <div style="margin-bottom:8px;"><label>Nova senha (opcional)</label><input id="admin-usuario-senha" type="password" style="width:100%"></div>
    <div style="margin-bottom:12px;"><label>Confirmar senha</label><input id="admin-usuario-senha-confirm" type="password" style="width:100%"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;">
      <button type="button" onclick="fecharModalUsuario()" style="padding:8px 16px;background:#ccc;border:none;border-radius:4px;cursor:pointer;">Cancelar</button>
      <button type="button" onclick="salvarEdicaoUsuarioAdmin()" style="padding:8px 16px;background:#0066cc;color:white;border:none;border-radius:4px;cursor:pointer;">Salvar</button>
    </div>
  </div>
</div>

<!-- Modal Editar Produto (admin) -->
<div id="modal-produto-admin-editar" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;justify-content:center;align-items:center;">
  <div style="background:white;padding:20px;border-radius:8px;max-width:600px;width:96%;">
    <h3 style="margin-top:0;">Editar Produto (Admin)</h3>
    <input type="hidden" id="admin-produto-id">
    <div style="margin-bottom:8px;"><label>Nome</label><input id="admin-produto-nome" style="width:100%"></div>
    <div style="margin-bottom:8px;"><label>Preço</label><input id="admin-produto-preco" style="width:100%"></div>
    <div style="margin-bottom:8px;"><label>Categoria</label><input id="admin-produto-categoria" style="width:100%"></div>
    <div style="margin-bottom:8px;"><label>Descrição</label><textarea id="admin-produto-descricao" style="width:100%"></textarea></div>
    <div style="margin-bottom:8px;"><label>URL da foto</label><input id="admin-produto-foto-url" style="width:100%"></div>
    <div style="margin-bottom:8px;"><label>Enviar nova imagem</label><input id="admin-produto-foto-arquivo" type="file" accept="image/*"></div>
    <div style="margin-bottom:8px;"><label><input type="checkbox" id="admin-produto-deleted"> Marcar como removido (is_deleted)</label></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;">
      <button type="button" onclick="fecharModalProdutoAdmin()" style="padding:8px 16px;background:#ccc;border:none;border-radius:4px;cursor:pointer;">Cancelar</button>
      <button type="button" onclick="salvarEdicaoProdutoAdmin()" style="padding:8px 16px;background:#0066cc;color:white;border:none;border-radius:4px;cursor:pointer;">Salvar</button>
    </div>
  </div>
</div>

<!-- Modal Editar Item de Pedido (admin) -->
<div id="modal-item-editar" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;justify-content:center;align-items:center;">
  <div style="background:white;padding:20px;border-radius:8px;max-width:420px;width:90%;">
    <h3 style="margin-top:0;">Editar Item do Pedido</h3>
    <input type="hidden" id="admin-pedido-id">
    <input type="hidden" id="admin-pedido-produto-id">
    <div style="margin-bottom:8px;"><label>Produto</label><div id="admin-item-produto-nome" style="font-weight:600"></div></div>
    <div style="margin-bottom:8px;"><label>Quantidade</label><input id="admin-item-quantidade" type="number" min="0" style="width:100%"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;">
      <button type="button" onclick="fecharModalItem()" style="padding:8px 16px;background:#ccc;border:none;border-radius:4px;cursor:pointer;">Cancelar</button>
      <button type="button" onclick="salvarEdicaoItemPedido()" style="padding:8px 16px;background:#0066cc;color:white;border:none;border-radius:4px;cursor:pointer;">Salvar</button>
    </div>
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

function postJsonAdmin(body) {
  if (body instanceof FormData) {
    return fetch('/admin_page.php', { method: 'POST', body: body }).then(r => r.json());
  }
  return fetch('/admin_page.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(body).toString()
  }).then(r => r.json());
}

// Usuário modal
function abrirModalEditarUsuario(id, nome, email, telefone, endereco, cartao, isSupplier) {
  document.getElementById('admin-usuario-id').value = id;
  document.getElementById('admin-usuario-nome').value = nome || '';
  document.getElementById('admin-usuario-email').value = email || '';
  document.getElementById('admin-usuario-telefone').value = telefone || '';
  document.getElementById('admin-usuario-endereco').value = endereco || '';
  document.getElementById('admin-usuario-cartao').value = cartao || '';
  document.getElementById('admin-usuario-senha').value = '';
  document.getElementById('admin-usuario-senha-confirm').value = '';
  document.getElementById('modal-usuario-editar').style.display = 'flex';
}
function fecharModalUsuario() { document.getElementById('modal-usuario-editar').style.display = 'none'; }
function salvarEdicaoUsuarioAdmin() {
  const id = document.getElementById('admin-usuario-id').value;
  const nome = document.getElementById('admin-usuario-nome').value.trim();
  const email = document.getElementById('admin-usuario-email').value.trim();
  const telefone = document.getElementById('admin-usuario-telefone').value.trim();
  const endereco = document.getElementById('admin-usuario-endereco').value.trim();
  const cartao = document.getElementById('admin-usuario-cartao').value.trim();
  const senha = document.getElementById('admin-usuario-senha').value;
  const senhaConfirm = document.getElementById('admin-usuario-senha-confirm').value;

  postJsonAdmin({ action: 'usuario_atualizar', usuario_id: id, nome, email, telefone, endereco, cartaocredito: cartao, senha, senha_confirm: senhaConfirm })
    .then(d => {
      if (d.erro) alert(d.erro); else { alert(d.mensagem || 'Atualizado'); location.reload(); }
    })
    .catch(() => alert('Erro ao atualizar usuário.'));
}

// Produto modal (admin)
function abrirModalEditarProdutoAdmin(id, nome, descricao, preco, categoria, fotoUrl, isDeleted) {
  document.getElementById('admin-produto-id').value = id;
  document.getElementById('admin-produto-nome').value = nome || '';
  document.getElementById('admin-produto-preco').value = preco || '';
  document.getElementById('admin-produto-categoria').value = categoria || '';
  document.getElementById('admin-produto-descricao').value = descricao || '';
  document.getElementById('admin-produto-foto-url').value = fotoUrl || '';
  document.getElementById('admin-produto-foto-arquivo').value = '';
  document.getElementById('admin-produto-deleted').checked = !!isDeleted;
  document.getElementById('modal-produto-admin-editar').style.display = 'flex';
}
function fecharModalProdutoAdmin() { document.getElementById('modal-produto-admin-editar').style.display = 'none'; }
function salvarEdicaoProdutoAdmin() {
  const id = document.getElementById('admin-produto-id').value;
  const nome = document.getElementById('admin-produto-nome').value.trim();
  const preco = document.getElementById('admin-produto-preco').value;
  const categoria = document.getElementById('admin-produto-categoria').value.trim();
  const descricao = document.getElementById('admin-produto-descricao').value.trim();
  const fotoUrl = document.getElementById('admin-produto-foto-url').value.trim();
  const arquivo = document.getElementById('admin-produto-foto-arquivo').files[0];
  const isDeleted = document.getElementById('admin-produto-deleted').checked;

  const form = new FormData();
  form.append('action', 'admin_produto_atualizar');
  form.append('produto_id', id);
  form.append('nome', nome);
  form.append('preco', preco);
  form.append('categoria', categoria);
  form.append('descricao', descricao);
  form.append('foto_url', fotoUrl);
  if (arquivo) form.append('foto_arquivo', arquivo);

  postJsonAdmin(form)
    .then(d => {
      if (d.erro) { alert(d.erro); return; }
      // Agora ajustar is_deleted se necessário
      postJsonAdmin({ action: 'admin_produto_set_deleted', produto_id: id, is_deleted: isDeleted ? '1' : '' })
        .then(() => { alert(d.mensagem || 'Produto atualizado'); location.reload(); })
        .catch(() => { alert('Produto atualizado, mas falha ao alterar flag de exclusão'); location.reload(); });
    })
    .catch(() => alert('Erro ao atualizar produto.'));
}

// Item do pedido modal
function abrirModalEditarItemPedido(pedidoId, produtoId, produtoNome, quantidade) {
  document.getElementById('admin-pedido-id').value = pedidoId;
  document.getElementById('admin-pedido-produto-id').value = produtoId;
  document.getElementById('admin-item-produto-nome').textContent = produtoNome || '';
  document.getElementById('admin-item-quantidade').value = quantidade || 0;
  document.getElementById('modal-item-editar').style.display = 'flex';
}
function fecharModalItem() { document.getElementById('modal-item-editar').style.display = 'none'; }
function salvarEdicaoItemPedido() {
  const pedidoId = document.getElementById('admin-pedido-id').value;
  const produtoId = document.getElementById('admin-pedido-produto-id').value;
  const quantidade = document.getElementById('admin-item-quantidade').value;
  postJsonAdmin({ action: 'pedido_item_atualizar', pedido_id: pedidoId, produto_id: produtoId, quantidade })
    .then(d => {
      if (d.erro) { alert(d.erro); return; }
      alert(d.mensagem || 'Quantidade atualizada');
      location.reload();
    })
    .catch(() => alert('Erro ao atualizar item do pedido.'));
}
</script>
<?php
$website_content = ob_get_clean();
include __DIR__ . '/template/index_template.php';
?>
