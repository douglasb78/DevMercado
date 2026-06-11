<?php
require_once __DIR__ . '/../dao/CarrinhoDAO.php';
require_once __DIR__ . '/../dao/ProdutoDAO.php';

class CarrinhoController {

    private CarrinhoDAO $dao;
    private ProdutoDAO $produtoDao;

    public function __construct() {
        $this->dao = new CarrinhoDAO();
        $this->produtoDao = new ProdutoDAO();
    }

    public function adicionar(): void {
        $produtoId  = (int) ($_POST['produto_id'] ?? 0);
        $quantidade = (int) ($_POST['quantidade'] ?? 1);

        if (!$produtoId || $quantidade < 1) {
            $this->erroJson('Dados invalidos.');
        }

        $produto = $this->produtoDao->buscarPorId($produtoId);
        if (!$produto || $produto->estoque <= 0) {
            $this->erroJson('Produto indisponivel.');
        }

        $carrinho = $this->lerCookie();
        $novaQuantidade = ($carrinho[$produtoId] ?? 0) + $quantidade;
        if ($novaQuantidade > $produto->estoque) {
            $this->erroJson("Estoque insuficiente. Disponivel: {$produto->estoque}.");
        }

        $carrinho[$produtoId] = $novaQuantidade;
        $this->salvarCookie($carrinho);

        if (!empty($_SESSION['usuario_id'])) {
            $this->sincronizarUsuario((int) $_SESSION['usuario_id'], $carrinho);
        }

        $this->jsonSucesso([
            'mensagem' => 'Adicionado ao carrinho!',
            'total_itens' => array_sum($carrinho),
        ]);
    }

    public function atualizar(): void {
        $produtoId  = (int) ($_POST['produto_id'] ?? 0);
        $quantidade = (int) ($_POST['quantidade'] ?? 0);

        $produto = $this->produtoDao->buscarPorId($produtoId);
        if (!$produto) {
            $this->erroJson('Produto nao encontrado.');
        }
        if ($quantidade > $produto->estoque) {
            $this->erroJson("Estoque insuficiente. Disponivel: {$produto->estoque}.");
        }

        $carrinho = $this->lerCookie();
        if ($quantidade <= 0) {
            unset($carrinho[$produtoId]);
        } else {
            $carrinho[$produtoId] = $quantidade;
        }
        $this->salvarCookie($carrinho);

        if (!empty($_SESSION['usuario_id'])) {
            $this->sincronizarUsuario((int) $_SESSION['usuario_id'], $carrinho);
        }

        $this->jsonSucesso(['mensagem' => 'Carrinho atualizado.']);
    }

    public function remover(): void {
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $carrinho = $this->lerCookie();
        unset($carrinho[$produtoId]);
        $this->salvarCookie($carrinho);

        if (!empty($_SESSION['usuario_id'])) {
            $this->sincronizarUsuario((int) $_SESSION['usuario_id'], $carrinho);
        }

        $this->jsonSucesso(['mensagem' => 'Item removido.']);
    }

    public function itens(): array {
        $carrinho = $this->lerCookie();

        if (empty($carrinho) && !empty($_SESSION['usuario_id'])) {
            foreach ($this->dao->listarPorUsuario((int) $_SESSION['usuario_id']) as $item) {
                $carrinho[$item->produtoId] = $item->quantidade;
            }
            $this->salvarCookie($carrinho);
        }

        $produtos = $this->produtoDao->listarPorIds(array_keys($carrinho));
        $itens = [];

        foreach ($produtos as $produto) {
            $quantidade = min((int) ($carrinho[$produto->id] ?? 0), $produto->estoque);
            if ($quantidade < 1) continue;

            $itens[] = new ItemCarrinho([
                'id' => 0,
                'usuario_id' => (int) ($_SESSION['usuario_id'] ?? 0),
                'produto_id' => $produto->id,
                'quantidade' => $quantidade,
                'produto_nome' => $produto->nome,
                'produto_preco' => $produto->preco,
                'produto_foto' => $produto->fotoUrl,
                'fornecedor_nome' => $produto->fornecedorNome,
                'estoque_disponivel' => $produto->estoque,
            ]);
        }

        return $itens;
    }

    public function sincronizarUsuario(int $usuarioId, ?array $carrinho = null): void {
        $carrinho = $carrinho ?? $this->lerCookie();
        $this->dao->limpar($usuarioId);

        foreach ($carrinho as $produtoId => $quantidade) {
            if ((int) $quantidade > 0) {
                $this->dao->adicionarOuIncrementar($usuarioId, (int) $produtoId, (int) $quantidade);
            }
        }
    }

    public function limparCookie(): void {
        $this->salvarCookie([]);
    }

    private function lerCookie(): array {
        $dados = json_decode($_COOKIE['devmercado_carrinho'] ?? '{}', true);
        if (!is_array($dados)) return [];

        $carrinho = [];
        foreach ($dados as $produtoId => $quantidade) {
            $pid = (int) $produtoId;
            $qtd = (int) $quantidade;
            if ($pid > 0 && $qtd > 0) {
                $carrinho[$pid] = $qtd;
            }
        }
        return $carrinho;
    }

    private function salvarCookie(array $carrinho): void {
        setcookie('devmercado_carrinho', json_encode($carrinho), [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        $_COOKIE['devmercado_carrinho'] = json_encode($carrinho);
    }

    private function jsonSucesso(array $dados): never {
        header('Content-Type: application/json');
        echo json_encode($dados);
        exit;
    }

    private function erroJson(string $mensagem, int $code = 400): never {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['erro' => $mensagem]);
        exit;
    }
}
