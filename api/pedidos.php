<?php
require_once __DIR__ . '/../dao/Database.php';
require_once __DIR__ . '/../dao/PedidoDAO.php';
require_once __DIR__ . '/../dao/ProdutoDAO.php';
require_once __DIR__ . '/../dao/UsuarioDAO.php';
require_once __DIR__ . '/../model/Pedido.php';

# Ligar e desligar a API de pedidos:

$PEDIDOS_API_ENABLED = true;


# Bloquear acesso se estiver desativada:
if (!$PEDIDOS_API_ENABLED) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'API desabilitada']);
    exit;
}

# API:
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

# Autenticação: a API usa a sessão do navegador. Um plugin REST do navegador
# compartilha o cookie de sessão, então o usuário precisa estar logado no site.
session_start();
$uid        = (int) ($_SESSION['usuario_id'] ?? 0);
$isAdmin    = !empty($_SESSION['usuario_admin']);
$isSupplier = !empty($_SESSION['usuario_supplier']);

if (!$uid) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado. Faça login para consultar pedidos.']);
    exit;
}

$pdo = Database::getInstance();
$pedidoDao = new PedidoDAO();
$produtoDao = new ProdutoDAO();
$usuarioDao = new UsuarioDAO();

$id = null;
if (isset($_GET['id'])) { $id = (int) $_GET['id']; }
elseif (isset($_GET['numero'])) { $id = (int) $_GET['numero']; }

$cliente = null;
if (isset($_GET['cliente'])) { $cliente = trim($_GET['cliente']); }
elseif (isset($_GET['nome'])) { $cliente = trim($_GET['nome']); }

if ($id) {
    $pedido = $pedidoDao->buscarPorId($id);
    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['error' => 'Pedido não encontrado']);
        exit;
    }

    # Autorização: admin vê qualquer pedido; o cliente vê apenas os próprios;
    # o fornecedor vê apenas pedidos que contenham itens fornecidos por ele.
    $ehDono       = $pedido->compradorId === $uid;
    $ehFornecedor = $isSupplier && $pedidoDao->pedidoPertenceAoFornecedor($id, $uid);
    if (!$isAdmin && !$ehDono && !$ehFornecedor) {
        http_response_code(403);
        echo json_encode(['error' => 'Você não tem permissão para consultar este pedido.']);
        exit;
    }

    $comprador = $usuarioDao->buscarPorId($pedido->compradorId);
    $compradorNome = $comprador ? $comprador->nome : ($pedido->compradorNome ?? '');

    $itens = $pedido->itens;
    $prodIds = array_values(array_unique(array_map(fn($it) => $it->produtoId, $itens)));
    $produtos = $produtoDao->listarPorIds($prodIds);
    $prodIndex = [];
    foreach ($produtos as $p) { $prodIndex[$p->id] = $p; }

    $itensOut = [];
    foreach ($itens as $it) {
        $p = $prodIndex[$it->produtoId] ?? null;
        $itensOut[] = [
            'produto_id' => $it->produtoId,
            'nome'       => $it->produtoNome ?: ($p ? $p->nome : ''),
            'fornecedor' => $p ? $p->fornecedorNome : null,
            'preco'      => $it->precoUnit,
            'quantidade' => $it->quantidade,
            'foto'       => $p ? ($p->fotoUrl ?: $it->produtoFoto) : $it->produtoFoto,
            'descricao'  => $p ? $p->descricao : '',
        ];
    }

    $out = [
        'id'                => $pedido->id,
        'comprador_nome'    => $compradorNome,
        'status'            => $pedido->status,
        'status_label'      => $pedido->statusLabel(),
        'data_estimada'     => $pedido->dataEstimadaFormatada(),
        'data_envio'        => $pedido->dataEnvioFormatada(),
        'data_cancelamento' => $pedido->dataCancelamentoFormatada(),
        'criado_em'         => $pedido->dataCompraFormatada(),
        'total'             => $pedido->total,
        'total_formatado'   => $pedido->totalFormatado(),
        'itens'             => $itensOut,
    ];

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

# As consultas que enumeram pedidos (por nome do cliente ou listagem geral) são
# restritas à administração da loja (Ator Interno: admin), pois percorrem pedidos
# de todos os clientes/fornecedores.
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas a administração da loja pode listar pedidos por cliente.']);
    exit;
}

# Busca por nome do cliente
if ($cliente) {
    $stmt = $pdo->prepare(
        'SELECT p.*, u.nome AS comprador_nome, u.endereco AS comprador_endereco
           FROM pedidos p
           JOIN usuarios u ON u.id = p.comprador_id
          WHERE u.nome ILIKE :nome
          ORDER BY p.criado_em DESC'
    );
    $stmt->execute([':nome' => '%' . $cliente . '%']);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $pedido = new Pedido($row);
        $pedido->itens = $pedidoDao->listarItensDoPedido($pedido->id);

        $prodIds = array_values(array_unique(array_map(fn($it) => $it->produtoId, $pedido->itens)));
        $produtos = $produtoDao->listarPorIds($prodIds);
        $prodIndex = [];
        foreach ($produtos as $p) { $prodIndex[$p->id] = $p; }

        $itensOut = [];
        foreach ($pedido->itens as $it) {
            $p = $prodIndex[$it->produtoId] ?? null;
            $itensOut[] = [
                'produto_id' => $it->produtoId,
                'nome'       => $it->produtoNome ?: ($p ? $p->nome : ''),
                'fornecedor' => $p ? $p->fornecedorNome : null,
                'preco'      => $it->precoUnit,
                'quantidade' => $it->quantidade,
                'foto'       => $p ? ($p->fotoUrl ?: $it->produtoFoto) : $it->produtoFoto,
                'descricao'  => $p ? $p->descricao : '',
            ];
        }

        $result[] = [
            'id'             => $pedido->id,
            'comprador_nome' => $row['comprador_nome'] ?? '',
            'status'         => $pedido->status,
            'criado_em'      => $pedido->dataCompraFormatada(),
            'total'          => $pedido->total,
            'itens'          => $itensOut,
        ];
    }

    echo json_encode(['pedidos' => $result], JSON_UNESCAPED_UNICODE);
    exit;
}

# Ter limite e offset na API:
if (isset($_GET['all'])) {
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    $pedidos = $pedidoDao->listarTodosPaginado($limit, $offset);

    $outArr = [];
    foreach ($pedidos as $pedido) {
        $compradorNome = $pedido->compradorNome ?: ($usuarioDao->buscarPorId($pedido->compradorId)->nome ?? '');

        $prodIds = array_values(array_unique(array_map(fn($it) => $it->produtoId, $pedido->itens)));
        $produtos = $produtoDao->listarPorIds($prodIds);
        $prodIndex = [];
        foreach ($produtos as $p) { $prodIndex[$p->id] = $p; }

        $itensOut = [];
        foreach ($pedido->itens as $it) {
            $p = $prodIndex[$it->produtoId] ?? null;
            $itensOut[] = [
                'produto_id' => $it->produtoId,
                'nome'       => $it->produtoNome ?: ($p ? $p->nome : ''),
                'fornecedor' => $p ? $p->fornecedorNome : null,
                'preco'      => $it->precoUnit,
                'quantidade' => $it->quantidade,
                'foto'       => $p ? ($p->fotoUrl ?: $it->produtoFoto) : $it->produtoFoto,
                'descricao'  => $p ? $p->descricao : '',
            ];
        }

        $outArr[] = [
            'id' => $pedido->id,
            'comprador_nome' => $compradorNome,
            'itens' => $itensOut,
        ];
    }

    echo json_encode(['pedidos' => $outArr], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Parâmetros inválidos. Use id/numero ou cliente/nome ou all=1.']);
exit;
