<?php
/*
  ===== API de Pedidos — como usar =====
  Ver um pedido:
    /api/pedidos.php/123
    /api/pedidos.php/id/123

  Todos os pedidos (só admin):
    /api/pedidos.php/all          ← RETORNA TODOS
    /api/pedidos.php/all/1        ← mantém comportamento antigo (limit 50)
    /api/pedidos.php/all/limit/100
    /api/pedidos.php/all/limit/0  ← também retorna todos
*/

require_once __DIR__ . '/../dao/Database.php';
require_once __DIR__ . '/../dao/PedidoDAO.php';
require_once __DIR__ . '/../dao/ProdutoDAO.php';
require_once __DIR__ . '/../dao/UsuarioDAO.php';
require_once __DIR__ . '/../model/Pedido.php';

$PEDIDOS_API_ENABLED = true;

if (!$PEDIDOS_API_ENABLED) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'API desabilitada']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

session_start();
$uid = (int) ($_SESSION['usuario_id'] ?? 0);
$isAdmin = !empty($_SESSION['usuario_admin']);
$isSupplier = !empty($_SESSION['usuario_supplier']);

if (!$uid) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$pdo = Database::getInstance();
$pedidoDao = new PedidoDAO();
$produtoDao = new ProdutoDAO();
$usuarioDao = new UsuarioDAO();

// === PARÂMETROS ===
$params = [];
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (empty($pathInfo) && isset($_SERVER['REQUEST_URI'])) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($uri, $script) === 0) {
        $pathInfo = substr($uri, strlen($script));
    }
}

$segmentos = array_values(array_filter(explode('/', $pathInfo), fn($s) => $s !== ''));

$id = null;
if (isset($segmentos[0]) && ctype_digit($segmentos[0])) {
    $id = (int) $segmentos[0];
    $segmentos = array_slice($segmentos, 1);
}

for ($i = 0; $i < count($segmentos); $i += 2) {
    $chave = strtolower($segmentos[$i] ?? '');
    $valor = $segmentos[$i + 1] ?? '';
    $params[$chave] = rawurldecode($valor);
}

if ($id === null) {
    $id = (int) ($params['id'] ?? $params['numero'] ?? 0);
}

$cliente = trim($params['cliente'] ?? $params['nome'] ?? '');

// ======================= BUSCA POR ID =======================
if ($id > 0) {
    // ... (código de busca por ID permanece igual ao que você tinha)
    $pedido = $pedidoDao->buscarPorId($id);
    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['error' => 'Pedido não encontrado']);
        exit;
    }

    $ehDono = $pedido->compradorId === $uid;
    $ehFornecedor = $isSupplier && $pedidoDao->pedidoPertenceAoFornecedor($id, $uid);

    if (!$isAdmin && !$ehDono && !$ehFornecedor) {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão.']);
        exit;
    }

    // ... resto do código de saída de um pedido (mantido igual) ...
    $comprador = $usuarioDao->buscarPorId($pedido->compradorId);
    $compradorNome = $comprador ? $comprador->nome : ($pedido->compradorNome ?? '');

    $itens = $pedido->itens;
    $prodIds = array_values(array_unique(array_map(fn($it) => $it->produtoId, $itens)));
    $produtos = $produtoDao->listarPorIds($prodIds);
    $prodIndex = array_column($produtos, null, 'id');

    $itensOut = [];
    foreach ($itens as $it) {
        $p = $prodIndex[$it->produtoId] ?? null;
        $itensOut[] = [
            'produto_id' => $it->produtoId,
            'nome' => $it->produtoNome ?: ($p ? $p->nome : ''),
            'fornecedor' => $p ? $p->fornecedorNome : null,
            'preco' => $it->precoUnit,
            'quantidade' => $it->quantidade,
            'foto' => $p ? ($p->fotoUrl ?? $it->produtoFoto) : $it->produtoFoto,
            'descricao' => $p ? $p->descricao : '',
        ];
    }

    echo json_encode([
        'id' => $pedido->id,
        'comprador_nome' => $compradorNome,
        'status' => $pedido->status,
        'status_label' => $pedido->statusLabel(),
        'data_estimada' => $pedido->dataEstimadaFormatada(),
        'data_envio' => $pedido->dataEnvioFormatada(),
        'data_cancelamento' => $pedido->dataCancelamentoFormatada(),
        'criado_em' => $pedido->dataCompraFormatada(),
        'total' => $pedido->total,
        'total_formatado' => $pedido->totalFormatado(),
        'itens' => $itensOut,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Apenas admin pode listar
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas admin pode listar pedidos.']);
    exit;
}

// Busca por cliente (mantido)
if ($cliente) {
    // ... seu código de busca por cliente (pode deixar como estava)
    // ...
}

// ======================= LISTAR TODOS =======================
{
    $limitStr = $params['limit'] ?? '';
    $offset = (int) ($params['offset'] ?? 0);

    // Se não informou limit, ou limit="", ou limit=0, ou "all" → retorna TODOS
    if ($limitStr === '' || $limitStr === '0' || strtolower($limitStr) === 'all') {
        $pedidos = $pedidoDao->listarTodos();
    } else {
        $limit = (int) $limitStr;
        if ($limit <= 0) $limit = 50;
        $pedidos = $pedidoDao->listarTodosPaginado($limit, $offset);
    }

    $outArr = [];
    foreach ($pedidos as $pedido) {
        $compradorNome = $pedido->compradorNome ?: ($usuarioDao->buscarPorId($pedido->compradorId)->nome ?? '');

        $prodIds = array_values(array_unique(array_map(fn($it) => $it->produtoId, $pedido->itens)));
        $produtos = $produtoDao->listarPorIds($prodIds);
        $prodIndex = array_column($produtos, null, 'id');

        $itensOut = [];
        foreach ($pedido->itens as $it) {
            $p = $prodIndex[$it->produtoId] ?? null;
            $itensOut[] = [
                'produto_id' => $it->produtoId,
                'nome' => $it->produtoNome ?: ($p ? $p->nome : ''),
                'fornecedor' => $p ? $p->fornecedorNome : null,
                'preco' => $it->precoUnit,
                'quantidade' => $it->quantidade,
                'foto' => $p ? ($p->fotoUrl ?? $it->produtoFoto) : $it->produtoFoto,
                'descricao' => $p ? $p->descricao : '',
            ];
        }

        $outArr[] = [
            'id' => $pedido->id,
            'comprador_nome' => $compradorNome,
            'status' => $pedido->status,
            'criado_em' => $pedido->dataCompraFormatada(),
            'total' => $pedido->total,
            'itens' => $itensOut,
        ];
    }

    echo json_encode(['pedidos' => $outArr], JSON_UNESCAPED_UNICODE);
}