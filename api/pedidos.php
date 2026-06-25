<?php
/*
    API de pedido jeito novo de usar

    agora a ideia e chamar pelo caminho da URL sem jogar parametro jogado no final

    exemplo:

    - buscar pedido pelo id:
      /api/pedidos/562334

    - mesma coisa, so que com mais curto:
      /api/lista/562334

    - buscar pedidos pelo nome do cliente:
      /api/pedidos/cliente/Maria
      /api/cliente/Maria

    - listar varios pedidos com paginacao:
      /api/pedidos/lista/50/0
      nesse caso 50 = limite e 0 = offset

    - listar usando o padrao:
      /api/pedidos
      /api/lista
*/
require_once __DIR__ . '/../dao/Database.php';
require_once __DIR__ . '/../dao/PedidoDAO.php';
require_once __DIR__ . '/../dao/ProdutoDAO.php';
require_once __DIR__ . '/../dao/UsuarioDAO.php';
require_once __DIR__ . '/../model/Pedido.php';

// Ligar e desligar a API de pedidos:
$PEDIDOS_API_ENABLED = true;

function responderJson(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function segmentoUrl(string $segmento): string {
    return trim(urldecode($segmento));
}

function segmentosApi(): array {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $segmentos = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    $apiIndex = array_search('api', $segmentos, true);

    if ($apiIndex !== false) {
        $segmentos = array_slice($segmentos, $apiIndex + 1);
    }

    if (($segmentos[0] ?? '') === 'pedidos.php') {
        array_shift($segmentos);
    }

    return array_map('segmentoUrl', $segmentos);
}

function rotaDaUrl(): ?array {
    $segmentos = segmentosApi();

    if (empty($segmentos)) {
        return ['acao' => 'listar', 'limit' => 50, 'offset' => 0];
    }

    $recurso = strtolower($segmentos[0]);
    $valor = $segmentos[1] ?? null;

    if (in_array($recurso, ['pedido', 'pedidos'], true)) {
        if ($valor === null || $valor === '' || $valor === 'lista') {
            return [
                'acao' => 'listar',
                'limit' => isset($segmentos[2]) ? max(1, (int) $segmentos[2]) : 50,
                'offset' => isset($segmentos[3]) ? max(0, (int) $segmentos[3]) : 0,
            ];
        }

        if ($valor === 'cliente' && isset($segmentos[2])) {
            return ['acao' => 'cliente', 'cliente' => $segmentos[2]];
        }

        if (ctype_digit($valor)) {
            return ['acao' => 'buscar', 'id' => (int) $valor];
        }
    }

    if ($recurso === 'lista') {
        if ($valor !== null && ctype_digit($valor)) {
            return ['acao' => 'buscar', 'id' => (int) $valor];
        }

        return [
            'acao' => 'listar',
            'limit' => isset($segmentos[1]) ? max(1, (int) $segmentos[1]) : 50,
            'offset' => isset($segmentos[2]) ? max(0, (int) $segmentos[2]) : 0,
        ];
    }

    if (in_array($recurso, ['cliente', 'clientes'], true) && $valor !== null) {
        return ['acao' => 'cliente', 'cliente' => $valor];
    }

    return null;
}

function itensDoPedidoParaJson(Pedido $pedido, ProdutoDAO $produtoDao): array {
    $itens = $pedido->itens;
    $prodIds = array_values(array_unique(array_map(fn($it) => $it->produtoId, $itens)));
    $produtos = $produtoDao->listarPorIds($prodIds);
    $prodIndex = [];

    foreach ($produtos as $p) {
        $prodIndex[$p->id] = $p;
    }

    $itensOut = [];
    foreach ($itens as $it) {
        $p = $prodIndex[$it->produtoId] ?? null;
        $itensOut[] = [
            'produto_id' => $it->produtoId,
            'nome' => $it->produtoNome ?: ($p ? $p->nome : ''),
            'fornecedor' => $p ? $p->fornecedorNome : null,
            'preco' => $it->precoUnit,
            'quantidade' => $it->quantidade,
            'foto' => $p ? ($p->fotoUrl ?: $it->produtoFoto) : $it->produtoFoto,
            'descricao' => $p ? $p->descricao : '',
        ];
    }

    return $itensOut;
}

function responderPedidoPorId(int $id, PedidoDAO $pedidoDao, ProdutoDAO $produtoDao, UsuarioDAO $usuarioDao): never {
    $pedido = $pedidoDao->buscarPorId($id);
    if (!$pedido) {
        responderJson(['error' => 'Pedido nao encontrado'], 404);
    }

    $comprador = $usuarioDao->buscarPorId($pedido->compradorId);
    $compradorNome = $comprador ? $comprador->nome : ($pedido->compradorNome ?? '');

    responderJson([
        'id' => $pedido->id,
        'comprador_nome' => $compradorNome,
        'status' => $pedido->status,
        'data_estimada' => $pedido->dataEstimadaFormatada(),
        'criado_em' => $pedido->dataCompraFormatada(),
        'total' => $pedido->total,
        'total_formatado' => $pedido->totalFormatado(),
        'itens' => itensDoPedidoParaJson($pedido, $produtoDao),
    ]);
}

function responderPedidosPorCliente(
    string $cliente,
    PDO $pdo,
    PedidoDAO $pedidoDao,
    ProdutoDAO $produtoDao
): never {
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

        $result[] = [
            'id' => $pedido->id,
            'comprador_nome' => $row['comprador_nome'] ?? '',
            'status' => $pedido->status,
            'criado_em' => $pedido->dataCompraFormatada(),
            'total' => $pedido->total,
            'itens' => itensDoPedidoParaJson($pedido, $produtoDao),
        ];
    }

    responderJson(['pedidos' => $result]);
}

function responderListaPedidos(
    int $limit,
    int $offset,
    PedidoDAO $pedidoDao,
    ProdutoDAO $produtoDao,
    UsuarioDAO $usuarioDao
): never {
    $pedidos = $pedidoDao->listarTodosPaginado($limit, $offset);

    $outArr = [];
    foreach ($pedidos as $pedido) {
        $comprador = $pedido->compradorNome ? null : $usuarioDao->buscarPorId($pedido->compradorId);
        $compradorNome = $pedido->compradorNome ?: ($comprador->nome ?? '');

        $outArr[] = [
            'id' => $pedido->id,
            'comprador_nome' => $compradorNome,
            'itens' => itensDoPedidoParaJson($pedido, $produtoDao),
        ];
    }

    responderJson([
        'pedidos' => $outArr,
        'paginacao' => [
            'limit' => $limit,
            'offset' => $offset,
        ],
    ]);
}

if (!$PEDIDOS_API_ENABLED) {
    header('Content-Type: application/json; charset=utf-8');
    responderJson(['error' => 'API desabilitada'], 404);
}

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['error' => 'Metodo nao permitido'], 405);
}

$pdo = Database::getInstance();
$pedidoDao = new PedidoDAO();
$produtoDao = new ProdutoDAO();
$usuarioDao = new UsuarioDAO();

$rota = rotaDaUrl();

if (!$rota) {
    responderJson([
        'error' => 'Rota invalida',
        'exemplos' => [
            '/api/pedidos/562334',
            '/api/lista/562334',
            '/api/pedidos/cliente/Maria',
            '/api/pedidos/lista/50/0',
        ],
    ], 400);
}

switch ($rota['acao']) {
    case 'buscar':
        responderPedidoPorId($rota['id'], $pedidoDao, $produtoDao, $usuarioDao);

    case 'cliente':
        responderPedidosPorCliente($rota['cliente'], $pdo, $pedidoDao, $produtoDao);

    case 'listar':
        responderListaPedidos($rota['limit'], $rota['offset'], $pedidoDao, $produtoDao, $usuarioDao);
}

responderJson(['error' => 'Rota invalida'], 400);
