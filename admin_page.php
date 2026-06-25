<?php
session_start();
require_once __DIR__ . '/include_all.php';

$usuarioDao = new UsuarioDAO();
$produtoDao = new ProdutoDAO();
$pedidoDao  = new PedidoDAO();

if (!empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_admin'])) {
    $usuarioLogado = $usuarioDao->buscarPorId((int) $_SESSION['usuario_id']);
    $_SESSION['usuario_admin'] = $usuarioLogado?->isAdmin ?? false;
}
if (empty($_SESSION['usuario_admin'])) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

function admin_link(string $aba, int $pagina, string $busca = ''): string {
    $url = '/admin_page.php?aba=' . urlencode($aba) . '&pagina=' . $pagina;
    if ($busca !== '') $url .= '&busca=' . urlencode($busca);
    return $url;
}

function admin_json(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function admin_processar_upload(string $campo): ?string {
    if (empty($_FILES[$campo]) || ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) admin_json(['erro' => 'Erro ao enviar imagem.'], 400);
    $info = @getimagesize($_FILES[$campo]['tmp_name']);
    if (!$info) admin_json(['erro' => 'O arquivo enviado precisa ser uma imagem.'], 400);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2]] ?? null;
    if (!$ext) admin_json(['erro' => 'Formato de imagem não permitido.'], 400);
    $pasta = __DIR__ . '/upload_media';
    if (!is_dir($pasta)) mkdir($pasta, 0775, true);
    $nomeArquivo = 'produto_admin_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $pasta . '/' . $nomeArquivo)) {
        admin_json(['erro' => 'Não foi possível salvar a imagem.'], 500);
    }
    return '/upload_media/' . $nomeArquivo;
}

const STATUS_PEDIDO = [
    'preparacao' => 'Em preparação',
    'transito'   => 'Em trânsito',
    'saiu'       => 'Saiu para entrega',
    'entregue'   => 'Entregue',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'usuario_criar') {
        $nome     = trim($_POST['nome'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $cartao   = trim($_POST['cartaocredito'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $isSupplier = isset($_POST['is_supplier']);
        $senha    = $_POST['senha'] ?? '';
        $senhaConfirm = $_POST['senha_confirm'] ?? '';

        if (!$nome || !$email)                                 admin_json(['erro' => 'Nome e e-mail são obrigatórios.'], 400);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))        admin_json(['erro' => 'E-mail inválido.'], 400);
        if (strlen($senha) < 6)                                admin_json(['erro' => 'A senha deve ter pelo menos 6 caracteres.'], 400);
        if ($senha !== $senhaConfirm)                          admin_json(['erro' => 'As senhas não coincidem.'], 400);
        if ($usuarioDao->emailJaExiste($email))                admin_json(['erro' => 'Este e-mail já está cadastrado.'], 400);

        try {
            $usuarioDao->inserir($nome, $email, password_hash($senha, PASSWORD_BCRYPT), $isSupplier, $telefone ?: null, $cartao ?: null, $endereco ?: null);
            admin_json(['mensagem' => 'Usuário criado com sucesso!']);
        } catch (Throwable $e) {
            admin_json(['erro' => 'Erro ao criar usuário.'], 500);
        }
    }

    if ($action === 'usuario_toggle_supplier') {
        $targetId = (int) ($_POST['id'] ?? 0);
        $desired  = !empty($_POST['is_supplier']);
        $target   = $usuarioDao->buscarPorId($targetId);
        if (!$target) admin_json(['erro' => 'Usuário não encontrado.'], 404);
        $usuarioDao->atualizar($targetId, $target->email, null, $target->telefone ?: null, $target->cartaocredito ?: null, $target->endereco ?: null, $target->nome ?: null, $desired);
        admin_json(['mensagem' => 'Atualizado.']);
    }

    if ($action === 'usuario_atualizar') {
        $uid      = (int) ($_POST['usuario_id'] ?? 0);
        $email    = trim($_POST['email'] ?? '');
        $nome     = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $cartao   = trim($_POST['cartaocredito'] ?? '');
        $endereco = trim($_POST['endereco'] ?? '');
        $isSupplier = isset($_POST['is_supplier']);
        $senha    = $_POST['senha'] ?? '';
        $senhaConfirm = $_POST['senha_confirm'] ?? '';

        if (!$uid || !$email || !$nome)                 admin_json(['erro' => 'ID, nome e e-mail são obrigatórios.'], 400);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) admin_json(['erro' => 'E-mail inválido.'], 400);

        $existing = $usuarioDao->buscarPorEmail($email);
        if ($existing && $existing->id !== $uid) admin_json(['erro' => 'Este e-mail já está cadastrado por outro usuário.'], 400);

        $senhaHash = null;
        if ($senha !== '') {
            if (strlen($senha) < 6)        admin_json(['erro' => 'A senha deve ter pelo menos 6 caracteres.'], 400);
            if ($senha !== $senhaConfirm)  admin_json(['erro' => 'As senhas não coincidem.'], 400);
            $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
        }

        try {
            $usuarioDao->atualizar($uid, $email, $senhaHash, $telefone ?: null, $cartao ?: null, $endereco ?: null, $nome ?: null, $isSupplier);
            admin_json(['mensagem' => 'Usuário atualizado com sucesso!']);
        } catch (Throwable $e) {
            admin_json(['erro' => 'Erro ao atualizar usuário.'], 500);
        }
    }

    if ($action === 'usuario_excluir') {
        $uid = (int) ($_POST['usuario_id'] ?? 0);
        if (!$uid)                              admin_json(['erro' => 'ID do usuário inválido.'], 400);
        if ($uid === (int) $_SESSION['usuario_id']) admin_json(['erro' => 'Você não pode excluir o próprio usuário.'], 400);
        try {
            $ok = $usuarioDao->softDelete($uid);
            admin_json(['mensagem' => $ok ? 'Usuário excluído.' : 'Usuário não encontrado.']);
        } catch (Throwable $e) {
            admin_json(['erro' => 'Erro ao excluir usuário.'], 500);
        }
    }

    if ($action === 'produto_criar') {
        $nome      = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco     = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $estoque   = max(0, (int) ($_POST['estoque'] ?? 0));
        $categoria = trim($_POST['categoria'] ?? '');
        $fornecedorId = (int) ($_POST['fornecedor_id'] ?? 0) ?: (int) $_SESSION['usuario_id'];

        if (!$nome || $preco <= 0)                  admin_json(['erro' => 'Nome e preço (maior que zero) são obrigatórios.'], 400);
        if (!$usuarioDao->buscarPorId($fornecedorId)) admin_json(['erro' => 'Fornecedor (ID) não encontrado.'], 400);

        $foto = admin_processar_upload('foto_arquivo') ?: trim($_POST['foto_url'] ?? '');
        try {
            $p = $produtoDao->inserir($fornecedorId, $nome, $descricao, $preco, $estoque, $categoria, $foto);
            admin_json(['mensagem' => 'Produto criado com sucesso!', 'id' => $p->id]);
        } catch (Throwable $e) {
            admin_json(['erro' => 'Erro ao criar produto.'], 500);
        }
    }

    if ($action === 'admin_produto_atualizar') {
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $nome      = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco     = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $categoria = trim($_POST['categoria'] ?? '');

        if (!$produtoId || !$nome) admin_json(['erro' => 'ID e nome do produto são obrigatórios.'], 400);

        $produto = $produtoDao->buscarPorId($produtoId, true);
        if (!$produto) admin_json(['erro' => 'Produto não encontrado.'], 404);

        $foto = admin_processar_upload('foto_arquivo') ?: (trim($_POST['foto_url'] ?? '') ?: $produto->fotoUrl);
        try {
            $produtoDao->atualizar($produtoId, $nome, $descricao, $preco, $categoria, $foto ?? '');
            admin_json(['mensagem' => 'Produto atualizado com sucesso!']);
        } catch (Throwable $e) {
            admin_json(['erro' => 'Erro ao atualizar produto.'], 500);
        }
    }

    if ($action === 'admin_produto_set_deleted') {
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $isDeleted = !empty($_POST['is_deleted']);
        if (!$produtoId) admin_json(['erro' => 'ID do produto inválido.'], 400);
        try {
            $ok = $produtoDao->setDeleted($produtoId, $isDeleted);
            admin_json(['mensagem' => $ok ? 'Operação realizada.' : 'Nenhuma alteração.']);
        } catch (Throwable $e) {
            admin_json(['erro' => 'Erro ao atualizar produto.'], 500);
        }
    }

    if ($action === 'pedido_status') {
        $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
        $status   = trim($_POST['status'] ?? '');
        $dataEstimada = trim($_POST['data_estimada'] ?? '');
        if (!$pedidoId || !array_key_exists($status, STATUS_PEDIDO)) admin_json(['erro' => 'Dados inválidos.'], 400);
        try {
            $ok = $pedidoDao->atualizarStatusAdmin($pedidoId, $status, $dataEstimada ?: null);
            admin_json(['mensagem' => $ok ? 'Pedido atualizado.' : 'Nada alterado.']);
        } catch (Throwable $e) {
            admin_json(['erro' => 'Erro ao atualizar pedido.'], 500);
        }
    }

    if ($action === 'pedido_cancelar') {
        $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
        if (!$pedidoId) admin_json(['erro' => 'ID inválido.'], 400);
        try {
            $ok = $pedidoDao->cancelarPedidoAdmin($pedidoId);
            admin_json(['mensagem' => $ok ? 'Pedido cancelado e estoque devolvido.' : 'Pedido já estava cancelado ou não existe.']);
        } catch (Throwable $e) {
            admin_json(['erro' => 'Erro ao cancelar pedido.'], 500);
        }
    }

    if ($action === 'pedido_item_atualizar') {
        $pedidoId  = (int) ($_POST['pedido_id'] ?? 0);
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $quant     = (int) ($_POST['quantidade'] ?? 0);
        if (!$pedidoId || !$produtoId) admin_json(['erro' => 'IDs inválidos.'], 400);
        try {
            $novoTotal = $pedidoDao->atualizarItemQuantidade($pedidoId, $produtoId, $quant);
            admin_json(['mensagem' => 'Quantidade atualizada.', 'novo_total' => $novoTotal, 'quantidade' => $quant]);
        } catch (Throwable $e) {
            admin_json(['erro' => $e->getMessage()], 400);
        }
    }

    admin_json(['erro' => 'Ação desconhecida.'], 400);
}

$aba = $_GET['aba'] ?? 'usuarios';
$map = ['clientes' => 'usuarios', 'fornecedores' => 'usuarios'];
if (isset($map[$aba])) $aba = $map[$aba];
if (!in_array($aba, ['usuarios', 'produtos', 'pedidos'], true)) $aba = 'usuarios';

$busca     = trim($_GET['busca'] ?? '');
$pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 25;
$offset    = ($pagina - 1) * $porPagina;

$usuarios = $produtos = $pedidos = [];
$total = 0;

if ($aba === 'usuarios') {
    if ($busca !== '') {
        $usuarios = $usuarioDao->buscarPaginado($busca, $porPagina, $offset);
        $total    = $usuarioDao->contarBusca($busca);
    } else {
        $usuarios = $usuarioDao->listarTodos($porPagina, $offset);
        $total    = $usuarioDao->contarTodos();
    }
} elseif ($aba === 'produtos') {
    if ($busca !== '') {
        $produtos = $produtoDao->buscarAdmin($busca, $porPagina, $offset);
        $total    = $produtoDao->contarBuscaAdmin($busca);
    } else {
        $produtos = $produtoDao->listarTodos($porPagina, $offset);
        $total    = $produtoDao->contar();
    }
} else { // pedidos
    if ($busca !== '') {
        $pedidos = $pedidoDao->buscarPaginado($busca, $porPagina, $offset);
        $total   = $pedidoDao->contarBusca($busca);
    } else {
        $pedidos = $pedidoDao->listarTodosPaginado($porPagina, $offset);
        $total   = $pedidoDao->contarTodos();
    }
}

$totalPaginas = max(1, (int) ceil($total / $porPagina));
$dados = $aba === 'usuarios' ? $usuarios : ($aba === 'produtos' ? $produtos : $pedidos);
$meuId = (int) $_SESSION['usuario_id'];

function admin_render_tabela(string $aba, array $dados, string $busca, int $pagina, int $totalPaginas, int $total, int $meuId): void {
?>
<div id="admin-tabela-wrap" data-total="<?= $total ?>">
<?php if ($aba === 'usuarios'): ?>
  <table class="admin-master-table admin-users-table">
    <thead>
      <tr>
        <th>ID</th><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Endereço</th><th>É fornecedor</th><th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($dados)): ?>
        <tr><td colspan="7" class="celula-vazia">Nenhum usuário encontrado.</td></tr>
      <?php else: foreach ($dados as $u): ?>
        <tr class="admin-master-row">
          <td data-label="ID"><?= $u->id ?></td>
          <td data-label="Nome"><?= htmlspecialchars($u->nome) ?></td>
          <td data-label="E-mail"><?= htmlspecialchars($u->email) ?></td>
          <td data-label="Telefone"><?= htmlspecialchars($u->telefone) ?></td>
          <td data-label="Endereço"><?= htmlspecialchars($u->endereco) ?></td>
          <td data-label="É fornecedor">
            <input type="checkbox" <?= $u->isSupplier ? 'checked' : '' ?> onchange="toggleFornecedor(<?= $u->id ?>, this.checked)">
          </td>
          <td data-label="Ações">
            <button type="button" onclick='abrirModalEditarUsuario(<?= $u->id ?>, <?= json_encode($u->nome) ?>, <?= json_encode($u->email) ?>, <?= json_encode($u->telefone) ?>, <?= json_encode($u->endereco) ?>, <?= json_encode($u->cartaocredito) ?>, <?= $u->isSupplier ? 'true' : 'false' ?>)'>Editar</button>
            <?php if ($u->id !== $meuId): ?>
              <button type="button" class="btn-perigo" onclick='excluirUsuario(<?= $u->id ?>, <?= json_encode($u->nome) ?>)'>Excluir</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

<?php elseif ($aba === 'produtos'): ?>
  <table class="admin-master-table admin-products-table">
    <thead>
      <tr>
        <th>ID</th><th>Foto</th><th>Produto</th><th>Fornecedor</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($dados)): ?>
        <tr><td colspan="8" class="celula-vazia">Nenhum produto encontrado.</td></tr>
      <?php else: foreach ($dados as $p): $fotoRaw = $p->fotoUrl ?: 'https://placehold.co/80x80?text=Prod'; ?>
        <tr class="admin-master-row">
          <td data-label="ID"><?= $p->id ?></td>
          <td data-label="Foto" class="td-fotos" onclick="event.stopPropagation()">
            <button type="button" class="thumb-button" onclick='abrirImagem(<?= json_encode($fotoRaw) ?>, <?= json_encode($p->nome) ?>)'>
              <img src="<?= htmlspecialchars($fotoRaw) ?>" alt="<?= htmlspecialchars($p->nome) ?>">
            </button>
          </td>
          <td data-label="Produto"><?= htmlspecialchars($p->nome) ?></td>
          <td data-label="Fornecedor"><?= htmlspecialchars($p->fornecedorNome) ?></td>
          <td data-label="Categoria"><?= htmlspecialchars($p->categoria) ?></td>
          <td data-label="Preço"><?= $p->precoFormatado() ?></td>
          <td data-label="Estoque"><?= $p->estoque ?></td>
          <td data-label="Ações">
            <button type="button" onclick='abrirModalEditarProdutoAdmin(<?= $p->id ?>, <?= json_encode($p->nome) ?>, <?= json_encode($p->descricao) ?>, <?= json_encode($p->preco) ?>, <?= json_encode($p->categoria) ?>, <?= json_encode($p->fotoUrl ?: "") ?>)'>Editar</button>
            <button type="button" class="btn-perigo" onclick='excluirProduto(<?= $p->id ?>, <?= json_encode($p->nome) ?>)'>Excluir</button>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

<?php else: /* pedidos */ ?>
  <table class="admin-master-table admin-orders-table">
    <thead>
      <tr>
        <th>Pedido</th><th>Comprador</th><th>Fornecedores</th><th>Fotos</th><th>Produtos</th><th>Status</th><th>Estimativa</th><th>Total</th><th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($dados)): ?>
        <tr><td colspan="9" class="celula-vazia">Nenhum pedido encontrado.</td></tr>
      <?php else: foreach ($dados as $pedido): ?>
        <tr class="admin-master-row admin-expand-row" onclick="PedidoDetalhe.toggleLinha('pedido-<?= $pedido->id ?>', <?= $pedido->id ?>, 'det-pedido-<?= $pedido->id ?>', true)">
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
          <td><?= htmlspecialchars(implode(', ', array_map(fn($i) => $i->produtoNome, array_slice($pedido->itens, 0, 3))) . (count($pedido->itens) > 3 ? ' +' . (count($pedido->itens) - 3) : '')) ?></td>
          <td><span class="status <?= htmlspecialchars($pedido->status) ?>"><?= htmlspecialchars($pedido->statusLabel()) ?></span></td>
          <td><?= $pedido->dataEstimadaFormatada() ?></td>
          <td><?= $pedido->totalFormatado() ?></td>
          <td data-label="Ações" onclick="event.stopPropagation()">
            <button type="button" onclick='abrirModalStatusPedido(<?= $pedido->id ?>, <?= json_encode($pedido->status) ?>, <?= json_encode($pedido->dataEstimada) ?>)'>Editar</button>
            <?php if ($pedido->status !== 'cancelado'): ?>
              <button type="button" class="btn-perigo" onclick='cancelarPedido(<?= $pedido->id ?>)'>Cancelar</button>
            <?php endif; ?>
          </td>
        </tr>
        <tr id="pedido-<?= $pedido->id ?>" class="admin-detail-row">
          <td colspan="9">
            <div class="pedido-itens-detalhe" id="det-pedido-<?= $pedido->id ?>"></div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
<?php endif; ?>

  <?php if ($totalPaginas > 1): ?>
    <div class="paginacao">
      <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
        <a class="<?= $i === $pagina ? 'active' : '' ?>" data-pagina="<?= $i ?>" href="<?= admin_link($aba, $i, $busca) ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
<?php
}

if (isset($_GET['fragmento'])) {
    admin_render_tabela($aba, $dados, $busca, $pagina, $totalPaginas, $total, $meuId);
    exit;
}

$placeholders = [
    'usuarios' => 'Buscar por código, nome ou e-mail...',
    'produtos' => 'Buscar por código, nome ou fornecedor...',
    'pedidos'  => 'Buscar por nº do pedido, cliente ou fornecedor...',
];

ob_start();
?>
<link rel="stylesheet" href="/css/pages/admin.css">

<div id="admin-page">
  <div class="admin-header">
    <h2>Admin</h2>
    <span id="admin-total"><?= $total ?> registro<?= $total === 1 ? '' : 's' ?></span>
  </div>

  <div class="admin-tabs">
    <a class="<?= $aba === 'usuarios' ? 'active' : '' ?>" href="<?= admin_link('usuarios', 1) ?>">Usuários</a>
    <a class="<?= $aba === 'produtos' ? 'active' : '' ?>" href="<?= admin_link('produtos', 1) ?>">Produtos</a>
    <a class="<?= $aba === 'pedidos'  ? 'active' : '' ?>" href="<?= admin_link('pedidos', 1) ?>">Pedidos</a>
  </div>

  <div class="admin-toolbar">
    <input type="text" id="admin-busca-input" class="admin-busca-input" autocomplete="off"
           placeholder="<?= htmlspecialchars($placeholders[$aba]) ?>" value="<?= htmlspecialchars($busca) ?>">
    <?php if ($aba === 'usuarios'): ?>
      <button type="button" class="btn-adicionar-admin" onclick="abrirModalCriarUsuario()">+ Adicionar usuário</button>
    <?php elseif ($aba === 'produtos'): ?>
      <button type="button" class="btn-adicionar-admin" onclick="abrirModalCriarProduto()">+ Adicionar produto</button>
    <?php endif; ?>
  </div>

  <?php admin_render_tabela($aba, $dados, $busca, $pagina, $totalPaginas, $total, $meuId); ?>
</div>

<div class="image-modal" id="image-modal" onclick="fecharImagem()">
  <div class="image-modal-content" onclick="event.stopPropagation()">
    <button type="button" onclick="fecharImagem()">Fechar</button>
    <img id="modal-img" src="" alt="">
    <span id="modal-title"></span>
  </div>
</div>

<div id="modal-usuario-criar" class="admin-modal">
  <div class="admin-modal-box">
    <h3>Adicionar Usuário</h3>
    <label>Nome</label><input id="novo-usuario-nome">
    <label>E-mail</label><input id="novo-usuario-email" type="email">
    <label>Telefone</label><input id="novo-usuario-telefone">
    <label>Endereço</label><input id="novo-usuario-endereco">
    <label>Cartão</label><input id="novo-usuario-cartao">
    <label>Senha</label><input id="novo-usuario-senha" type="password">
    <label>Confirmar senha</label><input id="novo-usuario-senha-confirm" type="password">
    <div class="check-line"><input type="checkbox" id="novo-usuario-supplier"><label>É fornecedor</label></div>
    <div class="admin-modal-acoes">
      <button type="button" class="btn-cancelar" onclick="fecharModal('modal-usuario-criar')">Cancelar</button>
      <button type="button" class="btn-salvar" onclick="criarUsuario()">Criar</button>
    </div>
  </div>
</div>

<div id="modal-usuario-editar" class="admin-modal">
  <div class="admin-modal-box">
    <h3>Editar Usuário</h3>
    <input type="hidden" id="admin-usuario-id">
    <label>Nome</label><input id="admin-usuario-nome">
    <label>E-mail</label><input id="admin-usuario-email" type="email">
    <label>Telefone</label><input id="admin-usuario-telefone">
    <label>Endereço</label><input id="admin-usuario-endereco">
    <label>Cartão</label><input id="admin-usuario-cartao">
    <label>Nova senha (opcional)</label><input id="admin-usuario-senha" type="password">
    <label>Confirmar senha</label><input id="admin-usuario-senha-confirm" type="password">
    <div class="check-line"><input type="checkbox" id="admin-usuario-supplier"><label>É fornecedor</label></div>
    <div class="admin-modal-acoes">
      <button type="button" class="btn-cancelar" onclick="fecharModal('modal-usuario-editar')">Cancelar</button>
      <button type="button" class="btn-salvar" onclick="salvarEdicaoUsuarioAdmin()">Salvar</button>
    </div>
  </div>
</div>

<div id="modal-produto-criar" class="admin-modal">
  <div class="admin-modal-box">
    <h3>Adicionar Produto</h3>
    <label>Nome</label><input id="novo-produto-nome">
    <label>Preço</label><input id="novo-produto-preco" placeholder="0.00">
    <label>Estoque</label><input id="novo-produto-estoque" type="number" min="0" value="0">
    <label>Categoria</label><input id="novo-produto-categoria">
    <label>Descrição</label><textarea id="novo-produto-descricao" rows="3"></textarea>
    <label>URL da foto</label><input id="novo-produto-foto-url">
    <label>Enviar imagem</label><input id="novo-produto-foto-arquivo" type="file" accept="image/*">
    <label>Fornecedor (ID) — opcional (vazio = você)</label><input id="novo-produto-fornecedor" type="number" min="1">
    <div class="admin-modal-acoes">
      <button type="button" class="btn-cancelar" onclick="fecharModal('modal-produto-criar')">Cancelar</button>
      <button type="button" class="btn-salvar" onclick="criarProduto()">Criar</button>
    </div>
  </div>
</div>

<div id="modal-produto-editar" class="admin-modal">
  <div class="admin-modal-box">
    <h3>Editar Produto</h3>
    <input type="hidden" id="admin-produto-id">
    <label>Nome</label><input id="admin-produto-nome">
    <label>Preço</label><input id="admin-produto-preco">
    <label>Categoria</label><input id="admin-produto-categoria">
    <label>Descrição</label><textarea id="admin-produto-descricao" rows="3"></textarea>
    <label>URL da foto</label><input id="admin-produto-foto-url">
    <label>Enviar nova imagem</label><input id="admin-produto-foto-arquivo" type="file" accept="image/*">
    <div class="admin-modal-acoes">
      <button type="button" class="btn-cancelar" onclick="fecharModal('modal-produto-editar')">Cancelar</button>
      <button type="button" class="btn-salvar" onclick="salvarEdicaoProdutoAdmin()">Salvar</button>
    </div>
  </div>
</div>

<div id="modal-pedido-status" class="admin-modal">
  <div class="admin-modal-box">
    <h3>Editar Pedido</h3>
    <input type="hidden" id="ped-status-id">
    <label>Status</label>
    <select id="ped-status-valor">
      <?php foreach (STATUS_PEDIDO as $valor => $rotulo): ?>
        <option value="<?= $valor ?>"><?= $rotulo ?></option>
      <?php endforeach; ?>
    </select>
    <label>Data estimada de entrega</label>
    <input type="date" id="ped-status-data">
    <div class="admin-modal-acoes">
      <button type="button" class="btn-cancelar" onclick="fecharModal('modal-pedido-status')">Cancelar</button>
      <button type="button" class="btn-salvar" onclick="salvarStatusPedido()">Salvar</button>
    </div>
  </div>
</div>

<div id="modal-item-editar" class="admin-modal">
  <div class="admin-modal-box">
    <h3>Editar Item do Pedido</h3>
    <input type="hidden" id="admin-pedido-id">
    <input type="hidden" id="admin-pedido-produto-id">
    <label>Produto</label>
    <div id="admin-item-produto-nome"></div>
    <label>Quantidade (0 remove o item)</label>
    <input id="admin-item-quantidade" type="number" min="0">
    <div class="admin-modal-acoes">
      <button type="button" class="btn-cancelar" onclick="fecharModal('modal-item-editar')">Cancelar</button>
      <button type="button" class="btn-salvar" onclick="salvarEdicaoItemPedido()">Salvar</button>
    </div>
  </div>
</div>

<script>
const ADMIN_ABA = <?= json_encode($aba) ?>;
let ADMIN_PAGINA = <?= $pagina ?>;

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
function abrirModal(id)  { document.getElementById(id).classList.add('visible'); }
function fecharModal(id) { document.getElementById(id).classList.remove('visible'); }

function abrirImagem(src, titulo) {
  document.getElementById('modal-img').src = src;
  document.getElementById('modal-title').textContent = titulo || '';
  document.getElementById('image-modal').classList.add('visible');
}
function fecharImagem() { document.getElementById('image-modal').classList.remove('visible'); }

const buscaInput = document.getElementById('admin-busca-input');
let buscaTimer;
if (buscaInput) {
  buscaInput.addEventListener('input', () => {
    clearTimeout(buscaTimer);
    buscaTimer = setTimeout(() => recarregarTabela(1), 300);
  });
}
document.getElementById('admin-page').addEventListener('click', (e) => {
  const a = e.target.closest('.paginacao a');
  if (a && a.dataset.pagina) {
    e.preventDefault();
    recarregarTabela(parseInt(a.dataset.pagina, 10));
  }
});
function recarregarTabela(pagina) {
  ADMIN_PAGINA = pagina || 1;
  const busca = buscaInput ? buscaInput.value : '';
  const qs = 'aba=' + encodeURIComponent(ADMIN_ABA) + '&busca=' + encodeURIComponent(busca) + '&pagina=' + ADMIN_PAGINA;
  fetch('/admin_page.php?fragmento=1&' + qs)
    .then(r => r.text())
    .then(html => {
      const wrap = document.getElementById('admin-tabela-wrap');
      if (!wrap) return;
      wrap.outerHTML = html;
      const novo = document.getElementById('admin-tabela-wrap');
      const t = novo ? parseInt(novo.dataset.total, 10) || 0 : 0;
      document.getElementById('admin-total').textContent = t + (t === 1 ? ' registro' : ' registros');
      if (window._thumbCarouselHelper) window._thumbCarouselHelper.initAll();
      history.replaceState(null, '', '/admin_page.php?' + qs);
    })
    .catch(() => {});
}

function abrirModalCriarUsuario() {
  ['nome','email','telefone','endereco','cartao','senha','senha-confirm'].forEach(c => document.getElementById('novo-usuario-' + c).value = '');
  document.getElementById('novo-usuario-supplier').checked = false;
  abrirModal('modal-usuario-criar');
}
function criarUsuario() {
  postJsonAdmin({
    action: 'usuario_criar',
    nome: document.getElementById('novo-usuario-nome').value.trim(),
    email: document.getElementById('novo-usuario-email').value.trim(),
    telefone: document.getElementById('novo-usuario-telefone').value.trim(),
    endereco: document.getElementById('novo-usuario-endereco').value.trim(),
    cartaocredito: document.getElementById('novo-usuario-cartao').value.trim(),
    senha: document.getElementById('novo-usuario-senha').value,
    senha_confirm: document.getElementById('novo-usuario-senha-confirm').value,
    is_supplier: document.getElementById('novo-usuario-supplier').checked ? '1' : ''
  }).then(d => {
    if (d.erro) { alert(d.erro); return; }
    fecharModal('modal-usuario-criar');
    recarregarTabela(1);
  }).catch(() => alert('Erro ao criar usuário.'));
}

function toggleFornecedor(id, checked) {
  postJsonAdmin({ action: 'usuario_toggle_supplier', id: id, is_supplier: checked ? '1' : '' })
    .then(d => { if (d.erro) { alert(d.erro); recarregarTabela(ADMIN_PAGINA); } })
    .catch(() => alert('Erro ao alterar fornecedor.'));
}

function abrirModalEditarUsuario(id, nome, email, telefone, endereco, cartao, isSupplier) {
  document.getElementById('admin-usuario-id').value = id;
  document.getElementById('admin-usuario-nome').value = nome || '';
  document.getElementById('admin-usuario-email').value = email || '';
  document.getElementById('admin-usuario-telefone').value = telefone || '';
  document.getElementById('admin-usuario-endereco').value = endereco || '';
  document.getElementById('admin-usuario-cartao').value = cartao || '';
  document.getElementById('admin-usuario-senha').value = '';
  document.getElementById('admin-usuario-senha-confirm').value = '';
  document.getElementById('admin-usuario-supplier').checked = !!isSupplier;
  abrirModal('modal-usuario-editar');
}
function excluirUsuario(id, nome) {
  if (!confirm('Excluir o usuário "' + nome + '"? Esta ação o desativa do sistema.')) return;
  postJsonAdmin({ action: 'usuario_excluir', usuario_id: id })
    .then(d => { if (d.erro) { alert(d.erro); return; } recarregarTabela(ADMIN_PAGINA); })
    .catch(() => alert('Erro ao excluir usuário.'));
}
function salvarEdicaoUsuarioAdmin() {
  postJsonAdmin({
    action: 'usuario_atualizar',
    usuario_id: document.getElementById('admin-usuario-id').value,
    nome: document.getElementById('admin-usuario-nome').value.trim(),
    email: document.getElementById('admin-usuario-email').value.trim(),
    telefone: document.getElementById('admin-usuario-telefone').value.trim(),
    endereco: document.getElementById('admin-usuario-endereco').value.trim(),
    cartaocredito: document.getElementById('admin-usuario-cartao').value.trim(),
    senha: document.getElementById('admin-usuario-senha').value,
    senha_confirm: document.getElementById('admin-usuario-senha-confirm').value,
    is_supplier: document.getElementById('admin-usuario-supplier').checked ? '1' : ''
  }).then(d => {
    if (d.erro) { alert(d.erro); return; }
    fecharModal('modal-usuario-editar');
    recarregarTabela(ADMIN_PAGINA);
  }).catch(() => alert('Erro ao atualizar usuário.'));
}

function abrirModalCriarProduto() {
  ['nome','preco','categoria','descricao','foto-url','fornecedor'].forEach(c => document.getElementById('novo-produto-' + c).value = '');
  document.getElementById('novo-produto-estoque').value = '0';
  document.getElementById('novo-produto-foto-arquivo').value = '';
  abrirModal('modal-produto-criar');
}
function criarProduto() {
  const form = new FormData();
  form.append('action', 'produto_criar');
  form.append('nome', document.getElementById('novo-produto-nome').value.trim());
  form.append('preco', document.getElementById('novo-produto-preco').value);
  form.append('estoque', document.getElementById('novo-produto-estoque').value);
  form.append('categoria', document.getElementById('novo-produto-categoria').value.trim());
  form.append('descricao', document.getElementById('novo-produto-descricao').value.trim());
  form.append('foto_url', document.getElementById('novo-produto-foto-url').value.trim());
  form.append('fornecedor_id', document.getElementById('novo-produto-fornecedor').value);
  const arq = document.getElementById('novo-produto-foto-arquivo').files[0];
  if (arq) form.append('foto_arquivo', arq);
  postJsonAdmin(form).then(d => {
    if (d.erro) { alert(d.erro); return; }
    fecharModal('modal-produto-criar');
    recarregarTabela(1);
  }).catch(() => alert('Erro ao criar produto.'));
}

function abrirModalEditarProdutoAdmin(id, nome, descricao, preco, categoria, fotoUrl) {
  document.getElementById('admin-produto-id').value = id;
  document.getElementById('admin-produto-nome').value = nome || '';
  document.getElementById('admin-produto-preco').value = preco || '';
  document.getElementById('admin-produto-categoria').value = categoria || '';
  document.getElementById('admin-produto-descricao').value = descricao || '';
  document.getElementById('admin-produto-foto-url').value = fotoUrl || '';
  document.getElementById('admin-produto-foto-arquivo').value = '';
  abrirModal('modal-produto-editar');
}
function salvarEdicaoProdutoAdmin() {
  const form = new FormData();
  form.append('action', 'admin_produto_atualizar');
  form.append('produto_id', document.getElementById('admin-produto-id').value);
  form.append('nome', document.getElementById('admin-produto-nome').value.trim());
  form.append('preco', document.getElementById('admin-produto-preco').value);
  form.append('categoria', document.getElementById('admin-produto-categoria').value.trim());
  form.append('descricao', document.getElementById('admin-produto-descricao').value.trim());
  form.append('foto_url', document.getElementById('admin-produto-foto-url').value.trim());
  const arq = document.getElementById('admin-produto-foto-arquivo').files[0];
  if (arq) form.append('foto_arquivo', arq);
  postJsonAdmin(form).then(d => {
    if (d.erro) { alert(d.erro); return; }
    fecharModal('modal-produto-editar');
    recarregarTabela(ADMIN_PAGINA);
  }).catch(() => alert('Erro ao atualizar produto.'));
}
function excluirProduto(id, nome) {
  if (!confirm('Excluir o produto "' + nome + '"? Ele deixa de aparecer na loja.')) return;
  postJsonAdmin({ action: 'admin_produto_set_deleted', produto_id: id, is_deleted: '1' })
    .then(d => { if (d.erro) { alert(d.erro); return; } recarregarTabela(ADMIN_PAGINA); })
    .catch(() => alert('Erro ao excluir produto.'));
}

function abrirModalStatusPedido(id, status, dataEstimada) {
  document.getElementById('ped-status-id').value = id;
  const sel = document.getElementById('ped-status-valor');
  sel.value = status;
  if (!sel.value) sel.selectedIndex = 0; // pedido cancelado: cai no 1º status
  document.getElementById('ped-status-data').value = dataEstimada || '';
  abrirModal('modal-pedido-status');
}
function salvarStatusPedido() {
  postJsonAdmin({
    action: 'pedido_status',
    pedido_id: document.getElementById('ped-status-id').value,
    status: document.getElementById('ped-status-valor').value,
    data_estimada: document.getElementById('ped-status-data').value
  }).then(d => {
    if (d.erro) { alert(d.erro); return; }
    fecharModal('modal-pedido-status');
    recarregarTabela(ADMIN_PAGINA);
  }).catch(() => alert('Erro ao atualizar pedido.'));
}
function cancelarPedido(id) {
  if (!confirm('Cancelar o pedido #' + id + '? O estoque dos itens será devolvido.')) return;
  postJsonAdmin({ action: 'pedido_cancelar', pedido_id: id })
    .then(d => { if (d.erro) { alert(d.erro); return; } alert(d.mensagem); recarregarTabela(ADMIN_PAGINA); })
    .catch(() => alert('Erro ao cancelar pedido.'));
}

function abrirModalEditarItemPedido(pedidoId, produtoId, produtoNome, quantidade) {
  document.getElementById('admin-pedido-id').value = pedidoId;
  document.getElementById('admin-pedido-produto-id').value = produtoId;
  document.getElementById('admin-item-produto-nome').textContent = produtoNome || '';
  document.getElementById('admin-item-quantidade').value = quantidade || 0;
  abrirModal('modal-item-editar');
}
function salvarEdicaoItemPedido() {
  postJsonAdmin({
    action: 'pedido_item_atualizar',
    pedido_id: document.getElementById('admin-pedido-id').value,
    produto_id: document.getElementById('admin-pedido-produto-id').value,
    quantidade: document.getElementById('admin-item-quantidade').value
  }).then(d => {
    if (d.erro) { alert(d.erro); return; }
    fecharModal('modal-item-editar');
    location.reload();
  }).catch(() => alert('Erro ao atualizar item do pedido.'));
}
</script>
<?php
$website_content = ob_get_clean();
include __DIR__ . '/template/index_template.php';
?>
