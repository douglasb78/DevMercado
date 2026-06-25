<?php
// Endpoint de busca rápida de produtos (autocomplete da navbar).
// GET /api/produtos_busca.php?q=termo  ->  { "produtos": [ ... ] }
require_once __DIR__ . '/../dao/ProdutoDAO.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$termo = trim($_GET['q'] ?? '');

// Só pesquisa a partir de 2 caracteres.
if (mb_strlen($termo) < 2) {
    echo json_encode(['produtos' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$dao = new ProdutoDAO();
$produtos = $dao->buscar($termo, 8, 0);

$out = [];
foreach ($produtos as $p) {
    $out[] = [
        'id'              => $p->id,
        'nome'            => $p->nome,
        'categoria'       => $p->categoria,
        'preco_formatado' => $p->precoFormatado(),
        'foto'            => $p->fotoUrl,
        'estoque'         => $p->estoque,
    ];
}

echo json_encode(['produtos' => $out], JSON_UNESCAPED_UNICODE);
exit;
