<?php
session_start();
require_once __DIR__ . '/include_all.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'produto_cadastrar') {
        (new ProdutoController())->cadastrar();
    } elseif ($action === 'pedido_atualizar_status') {
        (new PedidoController())->atualizarStatus();
    } elseif ($action === 'produto_atualizar_estoque') {
        (new ProdutoController())->atualizarEstoque();
    } elseif ($action === 'produto_atualizar') {
        (new ProdutoController())->atualizar();
    } elseif ($action === 'produto_excluir') {
        (new ProdutoController())->excluir();
    }
}

// Bloqueio de acesso: somente fornecedores logados acessam esta página.
if (!empty($_SESSION['usuario_id']) && empty($_SESSION['usuario_supplier'])) {
    $usuarioLogado = (new UsuarioDAO())->buscarPorId((int) $_SESSION['usuario_id']);
    $_SESSION['usuario_supplier'] = $usuarioLogado?->isSupplier ?? false;
}
if (empty($_SESSION['usuario_supplier'])) {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login_page.php');
    } else {
        http_response_code(403);
        echo 'Acesso negado.';
    }
    exit;
}

$produtoDao = new ProdutoDAO();
$pedidoDao  = new PedidoDAO();

$fid = (int) ($_SESSION['usuario_id'] ?? 0);
$produtos = $produtoDao->listarPorFornecedor($fid);

$paginaPedidos = max(1, (int) ($_GET['pagina_pedidos'] ?? 1));
$porPaginaPedidos = 10;
$offsetPedidos = ($paginaPedidos - 1) * $porPaginaPedidos;
$totalPedidos = $pedidoDao->contarPorFornecedor($fid);
$totalPaginasPedidos = max(1, (int) ceil($totalPedidos / $porPaginaPedidos));
$pedidos = $pedidoDao->listarPorFornecedorPaginado($fid, $porPaginaPedidos, $offsetPedidos);

ob_start();
?>
<link rel="stylesheet" href="/css/pages/manage.css">
<div id="manage-store">

  <div class="store-header">
    <h1>Minha Loja - <?= htmlspecialchars($_SESSION['usuario_nome'] ?? '') ?></h1>
  </div>

  <div class="store-menu">
    <button class="store-menu-btn active-panel" type="button" onclick="abrirPainel('cadastrar')">
      <span class="btn-titulo">Cadastrar Produto</span>
      <span class="btn-desc">Adicionar novos produtos.</span>
    </button>
    <button class="store-menu-btn" type="button" onclick="abrirPainel('entregas')">
      <span class="btn-titulo">Gerenciar Entregas</span>
      <span class="btn-desc">Pedidos em tabela compacta.</span>
    </button>
    <button class="store-menu-btn" type="button" onclick="abrirPainel('estoque')">
      <span class="btn-titulo">Gerenciar Estoque</span>
      <span class="btn-desc">Atualizar quantidades.</span>
    </button>
    <button class="store-menu-btn" type="button" onclick="abrirPainel('produtos')">
      <span class="btn-titulo">Produtos Cadastrados</span>
      <span class="btn-desc">Editar ou remover itens.</span>
    </button>
  </div>

  <div class="store-panel visible" id="painel-cadastrar">
    <h2>Cadastrar Produto</h2>
    <div id="msg-cadastrar" class="aviso-inline"></div>

    <div class="form-row">
      <div>
        <label>Nome do produto *</label>
        <input type="text" id="cad-nome" placeholder="Digite o nome do produto.">
      </div>
      <div>
        <label>Preço (R$) *</label>
        <input type="number" id="cad-preco" placeholder="0,00" min="0" step="0.01">
      </div>
    </div>

    <div class="form-row">
      <div>
        <label>Categoria</label>
        <select id="cad-categoria">
          <option value="">Selecione...</option>
          <?php foreach (ProdutoController::CATEGORIAS_PERMITIDAS as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Estoque inicial</label>
        <input type="number" id="cad-estoque" placeholder="0" min="0" value="0">
      </div>
    </div>

    <div>
      <label>Descrição</label>
      <textarea id="cad-descricao" placeholder="Descreva o produto..."></textarea>
    </div>

    <div>
      <label>URL da foto principal</label>
      <input type="text" id="cad-foto" placeholder="https://...">
    </div>

    <div>
      <label>Enviar imagem do produto</label>
      <input type="file" id="cad-foto-arquivo" accept="image/*">
    </div>

    <button class="btn-salvar" type="button" onclick="cadastrarProduto()">Cadastrar Produto</button>
  </div>

  <div class="store-panel" id="painel-entregas">
    <div class="compact-header">
      <h2>Gerenciar Entregas</h2>
      <span><?= $totalPedidos ?> pedido<?= $totalPedidos === 1 ? '' : 's' ?></span>
    </div>

    <?php if (empty($pedidos)): ?>
      <p class="estado-vazio">Nenhum pedido encontrado.</p>
    <?php else: ?>
      <table class="compact-master-table">
        <thead>
          <tr>
            <th>Pedido</th>
            <th>Cliente</th>
            <th>Endereço</th>
            <th>Produtos</th>
            <th>Fotos</th>
            <th>Status</th>
            <th>Entrega prevista</th>
            <th>Total</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pedidos as $pedido): ?>
            <?php
              $nomes = array_map(fn($i) => $i->produtoNome, $pedido->itens);
              $produtosResumo = implode(', ', array_slice($nomes, 0, 2));
              if (count($nomes) > 2) $produtosResumo .= ' +' . (count($nomes) - 2);
            ?>
            <tr class="compact-master-row" onclick="toggleDetalhe('entrega-<?= $pedido->id ?>')">
              <td data-label="Pedido">#<?= $pedido->id ?><br/><?= $pedido->dataCompraFormatada() ?></td>
              <td data-label="Cliente"><?= htmlspecialchars($pedido->compradorNome ?: 'Não informado') ?></td>
              <td data-label="Endereço"><?= htmlspecialchars($pedido->compradorEndereco ?: 'Não informado') ?></td>
              <td data-label="Produtos"><?= htmlspecialchars($produtosResumo) ?></td>
              <td data-label="Fotos" class="td-fotos">
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
              <td data-label="Status" onclick="event.stopPropagation()">
                <select id="status-<?= $pedido->id ?>">
                  <?php foreach (['preparacao'=>'Em preparação','transito'=>'Em trânsito','saiu'=>'Saiu para entrega','entregue'=>'Entregue','cancelado'=>'Cancelado'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $pedido->status === $val ? 'selected' : '' ?>>
                      <?= $label ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td data-label="Data" onclick="event.stopPropagation()">
                <input type="date" id="data-<?= $pedido->id ?>" value="<?= htmlspecialchars($pedido->dataEstimada) ?>">
              </td>
              <td data-label="Total"><?= $pedido->totalFormatado() ?></td>
              <td data-label="Ação" onclick="event.stopPropagation()">
                <button class="btn-salvar btn-compacto" type="button" onclick="salvarStatus(<?= $pedido->id ?>)">Salvar</button>
              </td>
            </tr>
            <tr id="entrega-<?= $pedido->id ?>" class="compact-detail-row">
              <td colspan="9">
                <table class="compact-detail-table">
                  <thead>
                    <tr>
                      <th>Produto</th>
                      <th>Qtd</th>
                      <th>Unitário</th>
                      <th>Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($pedido->itens as $item): ?>
                      <?php $fotoRaw = $item->produtoFoto ?: 'https://placehold.co/80x80?text=Prod'; $foto = htmlspecialchars($fotoRaw); ?>
                      <tr>
                        <td>
                          <span class="detail-product">
                            <img src="<?= $foto ?>" alt="<?= htmlspecialchars($item->produtoNome) ?>"
                                 onclick='abrirImagem(<?= json_encode($fotoRaw) ?>, <?= json_encode($item->produtoNome) ?>)'>
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

      <?php if ($totalPaginasPedidos > 1): ?>
        <div class="compact-pagination">
          <?php for ($i = 1; $i <= $totalPaginasPedidos; $i++): ?>
            <a class="<?= $i === $paginaPedidos ? 'active' : '' ?>" href="/manage_page.php?pagina_pedidos=<?= $i ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="store-panel" id="painel-estoque">
    <h2>Gerenciar Estoque</h2>

    <?php if (empty($produtos)): ?>
      <p class="estado-vazio">Nenhum produto cadastrado.</p>
    <?php else: ?>
      <table class="estoque-table">
        <thead>
          <tr>
            <th>Produto</th>
            <th>Categoria</th>
            <th>Estoque atual</th>
            <th>Novo estoque</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($produtos as $p): ?>
            <tr>
              <td data-label="Produto"><?= htmlspecialchars($p->nome) ?></td>
              <td data-label="Categoria"><?= htmlspecialchars($p->categoria) ?></td>
              <td data-label="Estoque atual" id="est-atual-<?= $p->id ?>"><?= $p->estoque ?></td>
              <td data-label="Novo estoque"><input type="number" id="est-novo-<?= $p->id ?>" value="<?= $p->estoque ?>" min="0"></td>
              <td data-label="Ação">
                <button class="btn-salvar btn-compacto" type="button" onclick="salvarEstoque(<?= $p->id ?>)">Salvar</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="store-panel" id="painel-produtos">
    <h2>Produtos Cadastrados</h2>

    <?php if (empty($produtos)): ?>
      <p class="estado-vazio">Nenhum produto cadastrado ainda.</p>
    <?php else: ?>
      <table class="compact-master-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Foto</th>
            <th>Nome</th>
            <th>Categoria</th>
            <th>Preço</th>
            <th>Estoque</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($produtos as $p): ?>
            <?php $fotoRaw = $p->fotoUrl ?: 'https://placehold.co/80x80?text=Prod'; $foto = htmlspecialchars($fotoRaw); ?>
            <tr id="card-<?= $p->id ?>">
              <td data-label="ID"><?= $p->id ?></td>
              <td data-label="Foto">
                <button type="button" class="thumb-button" onclick='abrirImagem(<?= json_encode($fotoRaw) ?>, <?= json_encode($p->nome) ?>)'>
                  <img src="<?= $foto ?>" alt="<?= htmlspecialchars($p->nome) ?>">
                </button>
              </td>
              <td data-label="Nome"><?= htmlspecialchars($p->nome) ?></td>
              <td data-label="Categoria"><?= htmlspecialchars($p->categoria) ?></td>
              <td data-label="Preço"><?= $p->precoFormatado() ?></td>
              <td data-label="Estoque"><?= $p->estoque ?></td>
              <td data-label="Ações">
                <button type="button" onclick="editarProduto(<?= $p->id ?>, '<?= htmlspecialchars(addslashes($p->nome)) ?>', '<?= htmlspecialchars(addslashes($p->descricao)) ?>', '<?= htmlspecialchars(addslashes($p->fotoUrl)) ?>')" class="btn-editar">Editar</button>
                <button type="button" onclick="confirmarExclusao(<?= $p->id ?>, '<?= htmlspecialchars(addslashes($p->nome)) ?>')" class="btn-excluir">Excluir</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div id="msg-global" class="toast"></div>

  <div id="modal-editar" class="modal-overlay">
    <div class="modal-janela">
      <h3>Editar Produto</h3>
      <div class="modal-campo">
        <label>Nome *</label>
        <input type="text" id="edit-nome">
      </div>
      <div class="modal-campo">
        <label>Descrição</label>
        <textarea id="edit-descricao"></textarea>
      </div>
      <div class="modal-campo">
        <label>URL da foto</label>
        <input type="text" id="edit-foto">
      </div>
      <div class="modal-campo">
        <label>Enviar nova imagem</label>
        <input type="file" id="edit-foto-arquivo" accept="image/*">
      </div>
      <div class="modal-acoes">
        <button type="button" class="btn-cancelar" onclick="fecharModalEditar()">Cancelar</button>
        <button type="button" class="btn-salvar" onclick="salvarEdicaoProduto()">Salvar</button>
      </div>
    </div>
  </div>
</div>

<div class="image-modal" id="image-modal" onclick="fecharImagem()">
  <div class="image-modal-content" onclick="event.stopPropagation()">
    <button type="button" onclick="fecharImagem()">Fechar</button>
    <img id="modal-img" src="" alt="">
    <strong id="modal-title"></strong>
  </div>
</div>

<script>
const paineis = ['cadastrar', 'entregas', 'estoque', 'produtos'];

function abrirPainel(id) {
  document.querySelectorAll('.store-menu-btn').forEach((btn, i) => {
    btn.classList.toggle('active-panel', paineis[i] === id);
  });
  paineis.forEach(p => {
    document.getElementById('painel-' + p).classList.toggle('visible', p === id);
  });
}

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

function mostrarMsg(msg, ok = true, elId = 'msg-global') {
  const el = document.getElementById(elId);
  el.textContent = msg;
  el.style.background = ok ? '#00a650' : '#c00';
  el.style.color = '#fff';
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 3500);
}

function postJson(body) {
  if (body instanceof FormData) {
    return fetch('/manage_page.php', { method: 'POST', body }).then(r => r.json());
  }

  return fetch('/manage_page.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(body).toString()
  }).then(r => r.json());
}

function cadastrarProduto() {
  const nome = document.getElementById('cad-nome').value.trim();
  const preco = document.getElementById('cad-preco').value;
  const estoque = document.getElementById('cad-estoque').value;
  const categoria = document.getElementById('cad-categoria').value;
  const descricao = document.getElementById('cad-descricao').value;
  const foto = document.getElementById('cad-foto').value.trim();
  const arquivo = document.getElementById('cad-foto-arquivo').files[0];

  if (!nome || !preco) {
    mostrarMsg('Nome e preço são obrigatórios.', false, 'msg-cadastrar');
    return;
  }

  const form = new FormData();
  form.append('action', 'produto_cadastrar');
  form.append('nome', nome);
  form.append('preco', preco);
  form.append('estoque', estoque);
  form.append('categoria', categoria);
  form.append('descricao', descricao);
  form.append('foto_url', foto);
  if (arquivo) form.append('foto_arquivo', arquivo);

  postJson(form)
    .then(data => {
      if (data.erro) {
        mostrarMsg(data.erro, false, 'msg-cadastrar');
      } else {
        mostrarMsg('✓ ' + data.mensagem, true, 'msg-cadastrar');
        ['cad-nome','cad-preco','cad-descricao','cad-foto'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('cad-foto-arquivo').value = '';
        document.getElementById('cad-estoque').value = '0';
        document.getElementById('cad-categoria').value = '';
        setTimeout(() => location.reload(), 1500);
      }
    })
    .catch(() => mostrarMsg('Erro ao cadastrar produto.', false, 'msg-cadastrar'));
}

function salvarStatus(pedidoId) {
  const status = document.getElementById('status-' + pedidoId).value;
  const data = document.getElementById('data-' + pedidoId).value;

  postJson({ action: 'pedido_atualizar_status', pedido_id: pedidoId, status, data_estimada: data })
    .then(d => mostrarMsg(d.erro ? d.erro : '✓ ' + d.mensagem, !d.erro))
    .catch(() => mostrarMsg('Erro ao atualizar status.', false));
}

function salvarEstoque(produtoId) {
  const novoEst = document.getElementById('est-novo-' + produtoId).value;

  postJson({ action: 'produto_atualizar_estoque', produto_id: produtoId, novo_estoque: novoEst })
    .then(d => {
      if (!d.erro) {
        document.getElementById('est-atual-' + produtoId).textContent = novoEst;
        mostrarMsg('✓ Estoque atualizado!');
      } else {
        mostrarMsg(d.erro, false);
      }
    })
    .catch(() => mostrarMsg('Erro ao atualizar estoque.', false));
}

let produtoEmEdicao = null;

function editarProduto(produtoId, nome, descricao, foto) {
  produtoEmEdicao = produtoId;
  document.getElementById('edit-nome').value = nome;
  document.getElementById('edit-descricao').value = descricao;
  document.getElementById('edit-foto').value = foto;
  document.getElementById('edit-foto-arquivo').value = '';
  document.getElementById('modal-editar').style.display = 'flex';
}

function fecharModalEditar() {
  document.getElementById('modal-editar').style.display = 'none';
  produtoEmEdicao = null;
}

function confirmarExclusao(produtoId, nomeProduto) {
  if (confirm(`Tem certeza que deseja excluir o produto "${nomeProduto}"?`)) {
    excluirProduto(produtoId);
  }
}

function excluirProduto(produtoId) {
  postJson({ action: 'produto_excluir', produto_id: produtoId })
    .then(d => {
      if (!d.erro) {
        mostrarMsg('✓ Produto excluído com sucesso!', true);
        document.getElementById('card-' + produtoId)?.remove();
      } else {
        mostrarMsg(d.erro, false);
      }
    })
    .catch(() => mostrarMsg('Erro ao excluir o produto.', false));
}

function salvarEdicaoProduto() {
  if (!produtoEmEdicao) return;

  const nome = document.getElementById('edit-nome').value.trim();
  const descricao = document.getElementById('edit-descricao').value.trim();
  const foto = document.getElementById('edit-foto').value.trim();
  const arquivo = document.getElementById('edit-foto-arquivo').files[0];

  if (!nome) {
    mostrarMsg('Nome é obrigatório.', false);
    return;
  }

  const form = new FormData();
  form.append('action', 'produto_atualizar');
  form.append('produto_id', produtoEmEdicao);
  form.append('nome', nome);
  form.append('descricao', descricao);
  form.append('foto_url', foto);
  if (arquivo) form.append('foto_arquivo', arquivo);

  postJson(form)
    .then(d => {
      if (!d.erro) {
        mostrarMsg('✓ ' + d.mensagem);
        fecharModalEditar();
        setTimeout(() => location.reload(), 1500);
      } else {
        mostrarMsg(d.erro, false);
      }
    })
    .catch(() => mostrarMsg('Erro ao atualizar produto.', false));
}

window.addEventListener('click', (e) => {
  const modal = document.getElementById('modal-editar');
  if (e.target === modal) fecharModalEditar();
});

abrirPainel(new URLSearchParams(window.location.search).has('pagina_pedidos') ? 'entregas' : 'cadastrar');
</script>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
