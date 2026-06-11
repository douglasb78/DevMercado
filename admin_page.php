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
$abasPermitidas = ['clientes', 'fornecedores', 'produtos'];
if (!in_array($aba, $abasPermitidas, true)) {
    $aba = 'clientes';
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 10;
$offset = ($pagina - 1) * $porPagina;

$produtoDao = new ProdutoDAO();

$clientes = $fornecedores = $produtos = [];
$total = 0;

if ($aba === 'clientes') {
    $clientes = $usuarioDao->listarPorTipo(false, $porPagina, $offset);
    $total = $usuarioDao->contarPorTipo(false);
} elseif ($aba === 'fornecedores') {
    $fornecedores = $usuarioDao->listarPorTipo(true, $porPagina, $offset);
    $total = $usuarioDao->contarPorTipo(true);
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
<style>
#admin-page {
  width: 80vw;
  margin: 0 auto;
  padding: 20px;
  background: #eee;
  border: 1px solid #444;
  flex-grow: 1;
}
#admin-page .admin-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
#admin-page .admin-tabs a,
#admin-page .paginacao a {
  border: 1px solid #444;
  background: #fff;
  color: #222;
  padding: 9px 14px;
  text-decoration: none;
}
#admin-page .admin-tabs a.active,
#admin-page .paginacao a.active {
  background: #0066cc;
  border-color: #0066cc;
  color: #fff;
}
#admin-page table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}
#admin-page th,
#admin-page td {
  border: 1px solid #ccc;
  padding: 10px;
  text-align: left;
  font-size: 0.9rem;
}
#admin-page th {
  background: #f5f5f5;
}
#admin-page .paginacao {
  margin-top: 18px;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
@media (max-width: 700px) {
  #admin-page { width: auto; overflow-x: auto; }
}
</style>

<div id="admin-page">
  <h2>Admin</h2>

  <div class="admin-tabs">
    <a class="<?= $aba === 'clientes' ? 'active' : '' ?>" href="<?= admin_link('clientes', 1) ?>">Clientes</a>
    <a class="<?= $aba === 'fornecedores' ? 'active' : '' ?>" href="<?= admin_link('fornecedores', 1) ?>">Fornecedores</a>
    <a class="<?= $aba === 'produtos' ? 'active' : '' ?>" href="<?= admin_link('produtos', 1) ?>">Produtos</a>
  </div>

  <?php if ($aba === 'clientes' || $aba === 'fornecedores'): ?>
    <?php $usuarios = $aba === 'clientes' ? $clientes : $fornecedores; ?>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Nome</th>
          <th>E-mail</th>
          <th>Telefone</th>
          <th>Endereco</th>
          <th>Criado em</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $usuario): ?>
          <tr>
            <td><?= $usuario->id ?></td>
            <td><?= htmlspecialchars($usuario->nome) ?></td>
            <td><?= htmlspecialchars($usuario->email) ?></td>
            <td><?= htmlspecialchars($usuario->telefone) ?></td>
            <td><?= htmlspecialchars($usuario->endereco) ?></td>
            <td><?= htmlspecialchars($usuario->criadoEm) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Produto</th>
          <th>Fornecedor</th>
          <th>Categoria</th>
          <th>Preco</th>
          <th>Estoque</th>
          <th>Foto URL</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($produtos as $produto): ?>
          <tr>
            <td><?= $produto->id ?></td>
            <td><?= htmlspecialchars($produto->nome) ?></td>
            <td><?= htmlspecialchars($produto->fornecedorNome) ?></td>
            <td><?= htmlspecialchars($produto->categoria) ?></td>
            <td><?= $produto->precoFormatado() ?></td>
            <td><?= $produto->estoque ?></td>
            <td><?= htmlspecialchars($produto->fotoUrl) ?></td>
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
<?php
$website_content = ob_get_clean();
include __DIR__ . '/template/index_template.php';
?>
