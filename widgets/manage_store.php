<?php
require_once __DIR__ . '/../dao/ProdutoDAO.php';
require_once __DIR__ . '/../dao/PedidoDAO.php';

$produtoDao = new ProdutoDAO();
$pedidoDao  = new PedidoDAO();

$fid      = (int) ($_SESSION['usuario_id'] ?? 0);
$produtos = $produtoDao->listarPorFornecedor($fid);
$pedidos  = $pedidoDao->listarPorFornecedor($fid);

$categorias = [
    'Eletrodomésticos', 'Celulares & Telefonia', 'Móveis',
    'Computadores', 'Notebooks', 'Alimentos & Bebidas',
    'Automóveis', 'Outros',
];
?>
<link rel="stylesheet" href="/widgets/manage_store.css">
<link rel="stylesheet" href="../index.css">
<div id="manage_store">

  <div class="store-header">
    <h1>Minha Loja — <?= htmlspecialchars($_SESSION['usuario_nome'] ?? '') ?></h1>
  </div>

  <div class="store-menu">
    <button class="store-menu-btn active-panel" type="button" onclick="abrirPainel('cadastrar')">
      <span class="btn-titulo">Cadastrar Produto</span>
      <span class="btn-desc">Adicionar novos produtos à loja.</span>
    </button>
    <button class="store-menu-btn" type="button" onclick="abrirPainel('entregas')">
      <span class="btn-titulo">Gerenciar Entregas</span>
      <span class="btn-desc">Atualizar o status dos pedidos.</span>
    </button>
    <button class="store-menu-btn" type="button" onclick="abrirPainel('estoque')">
      <span class="btn-titulo">Gerenciar Estoque</span>
      <span class="btn-desc">Definir quantidade disponível de cada produto.</span>
    </button>
    <button class="store-menu-btn" type="button" onclick="abrirPainel('produtos')">
      <span class="btn-titulo">Ver Produtos Cadastrados</span>
      <span class="btn-desc">Visualizar, editar ou remover produtos.</span>
    </button>
  </div>

  <div class="store-panel visible" id="painel-cadastrar">
    <h2>Cadastrar Produto</h2>

    <div id="msg-cadastrar" style="display:none;padding:10px;margin-bottom:12px;font-weight:600;"></div>

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
          <?php foreach ($categorias as $cat): ?>
            <option><?= htmlspecialchars($cat) ?></option>
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

    <button class="btn-salvar" type="button" onclick="cadastrarProduto()">Cadastrar Produto</button>
  </div>

  <div class="store-panel" id="painel-entregas">
    <h2>Gerenciar Entregas</h2>

    <?php if (empty($pedidos)): ?>
      <p style="color:#666;padding:20px;text-align:center;">Nenhum pedido encontrado.</p>
    <?php else: ?>
      <?php foreach ($pedidos as $pedido): ?>
        <div class="pedido-card" id="pedido-<?= $pedido->id ?>">
          <img src="<?= htmlspecialchars($pedido->itens[0]->produtoFoto ?? 'https://placehold.co/60x60?text=Prod') ?>"
               alt="Produto" class="produto-img">

          <div class="info-principal">
            <strong>
              <?php $nomes = array_map(fn($i) => $i->produtoNome, $pedido->itens);
                    echo htmlspecialchars(implode(', ', array_slice($nomes, 0, 2)));
                    if (count($nomes) > 2) echo ' +' . (count($nomes) - 2); ?>
            </strong><br>
            <span class="data-compra">
              Pedido #<?= $pedido->id ?> · <?= $pedido->dataCompraFormatada() ?> ·
              <?= $pedido->totalFormatado() ?>
            </span>
          </div>

          <div class="acoes-entrega">
            <select id="status-<?= $pedido->id ?>">
              <?php foreach (['preparacao'=>'Em preparação','transito'=>'Em trânsito','saiu'=>'Saiu para entrega','entregue'=>'Entregue'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $pedido->status === $val ? 'selected' : '' ?>>
                  <?= $label ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div>
              <label style="font-size:0.8rem;color:#666;">Data prevista:</label>
              <input type="date" id="data-<?= $pedido->id ?>"
                     value="<?= htmlspecialchars($pedido->dataEstimada) ?>">
            </div>
            <button class="btn-salvar" type="button" style="padding:6px 16px;font-size:0.85rem;"
                    onclick="salvarStatus(<?= $pedido->id ?>)">Salvar</button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="store-panel" id="painel-estoque">
    <h2>Gerenciar Estoque</h2>

    <?php if (empty($produtos)): ?>
      <p style="color:#666;padding:20px;text-align:center;">Nenhum produto cadastrado.</p>
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
              <td><?= htmlspecialchars($p->nome) ?></td>
              <td><?= htmlspecialchars($p->categoria) ?></td>
              <td id="est-atual-<?= $p->id ?>"><?= $p->estoque ?></td>
              <td><input type="number" id="est-novo-<?= $p->id ?>"
                         value="<?= $p->estoque ?>" min="0"></td>
              <td>
                <button class="btn-salvar" type="button" style="padding:6px 14px;font-size:0.85rem;"
                        onclick="salvarEstoque(<?= $p->id ?>)">Salvar</button>
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
      <p style="color:#666;padding:20px;text-align:center;">Nenhum produto cadastrado ainda.</p>
    <?php else: ?>
      <div class="produtos-cadastrados-grid" id="grid-produtos">
        <?php foreach ($produtos as $p): ?>
          <div class="produto-cadastrado-card" id="card-<?= $p->id ?>">
            <img src="<?= htmlspecialchars($p->fotoUrl ?: 'https://placehold.co/200x140?text=Sem+Foto') ?>"
                 alt="<?= htmlspecialchars($p->nome) ?>">
            <span class="nome"><?= htmlspecialchars($p->nome) ?></span>
            <span class="preco"><?= $p->precoFormatado() ?></span>
            <span class="estoque-badge">Estoque: <?= $p->estoque ?> unidades</span>
            <div class="card-acoes">
              <button type="button" onclick="editarProduto(<?= $p->id ?>, '<?= htmlspecialchars(addslashes($p->nome)) ?>', '<?= htmlspecialchars(addslashes($p->descricao)) ?>', '<?= htmlspecialchars(addslashes($p->fotoUrl)) ?>')" class="btn-editar">Editar</button>
              <button type="button" 
                onclick="confirmarExclusao(<?= $p->id ?>, '<?= htmlspecialchars(addslashes($p->nome)) ?>')" 
                class="btn-excluir">
                Excluir
            </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div id="msg-global" style="display:none;position:fixed;bottom:24px;right:24px;
       padding:14px 24px;border-radius:4px;font-weight:600;z-index:9999;
       box-shadow:0 4px 12px rgba(0,0,0,0.2);"></div>

  <div id="modal-editar" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:10000;justify-content:center;align-items:center;">
    <div style="background:white;padding:30px;border-radius:8px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto;">
      <h3 style="margin-top:0;">Editar Produto</h3>
      
      <div style="margin-bottom:15px;">
        <label style="display:block;font-weight:600;margin-bottom:5px;">Nome *</label>
        <input type="text" id="edit-nome" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
      </div>

      <div style="margin-bottom:15px;">
        <label style="display:block;font-weight:600;margin-bottom:5px;">Descrição</label>
        <textarea id="edit-descricao" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;min-height:100px;"></textarea>
      </div>

      <div style="margin-bottom:15px;">
        <label style="display:block;font-weight:600;margin-bottom:5px;">URL da foto</label>
        <input type="text" id="edit-foto" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="fecharModalEditar()" style="padding:8px 16px;background:#ccc;border:none;border-radius:4px;cursor:pointer;">Cancelar</button>
        <button type="button" onclick="salvarEdicaoProduto()" style="padding:8px 16px;background:#0066cc;color:white;border:none;border-radius:4px;cursor:pointer;">Salvar</button>
      </div>
    </div>
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

function mostrarMsg(msg, ok = true, elId = 'msg-global') {
  const el = document.getElementById(elId);
  el.textContent = msg;
  el.style.background = ok ? '#00a650' : '#c00';
  el.style.color = '#fff';
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 3500);
}

function postJson(body) {
  return fetch('/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(body).toString()
  }).then(r => r.json());
}

function cadastrarProduto() {
  const nome      = document.getElementById('cad-nome').value.trim();
  const preco     = document.getElementById('cad-preco').value;
  const estoque   = document.getElementById('cad-estoque').value;
  const categoria = document.getElementById('cad-categoria').value;
  const descricao = document.getElementById('cad-descricao').value;
  const foto      = document.getElementById('cad-foto').value.trim();

  if (!nome || !preco) {
    mostrarMsg('Nome e preço são obrigatórios.', false, 'msg-cadastrar');
    return;
  }

  postJson({ action: 'produto_cadastrar', nome, preco, estoque, categoria, descricao, foto_url: foto })
    .then(data => {
      if (data.erro) {
        mostrarMsg(data.erro, false, 'msg-cadastrar');
      } else {
        mostrarMsg('✓ ' + data.mensagem, true, 'msg-cadastrar');
        ['cad-nome','cad-preco','cad-descricao','cad-foto'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('cad-estoque').value = '0';
        document.getElementById('cad-categoria').value = '';
        setTimeout(() => location.reload(), 1500);
      }
    })
    .catch(() => mostrarMsg('Erro ao cadastrar produto.', false, 'msg-cadastrar'));
}

function salvarStatus(pedidoId) {
  const status = document.getElementById('status-' + pedidoId).value;
  const data   = document.getElementById('data-'   + pedidoId).value;

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
  document.getElementById('modal-editar').style.display = 'flex';
}

function fecharModalEditar() {
  document.getElementById('modal-editar').style.display = 'none';
  produtoEmEdicao = null;
}

function confirmarExclusao(produtoId, nomeProduto) {
    if (confirm(`Tem certeza que deseja excluir o produto "${nomeProduto}"?\n\nEssa ação não pode ser desfeita facilmente.`)) {
        excluirProduto(produtoId);
    }
}

function excluirProduto(produtoId) {
    postJson({
        action: 'produto_excluir',
        produto_id: produtoId
    })
    .then(d => {
        if (!d.erro) {
            mostrarMsg('✓ Produto excluído com sucesso!', true);
            // remove o card da interface
            const card = document.getElementById('card-' + produtoId);
            if (card) {
                card.style.transition = 'opacity 0.4s';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 400);
            }
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

  if (!nome) {
    mostrarMsg('Nome é obrigatório.', false);
    return;
  }

  postJson({
    action: 'produto_atualizar',
    produto_id: produtoEmEdicao,
    nome,
    descricao,
    foto_url: foto
  })
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
  if (e.target === modal) {
    fecharModalEditar();
  }
});

abrirPainel('cadastrar');
</script>
