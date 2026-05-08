<?php
require_once __DIR__ . '/../dao/ProdutoDAO.php';

class ProdutoController {

    private ProdutoDAO $dao;

    public function __construct() {
        $this->dao = new ProdutoDAO();
    }

    public function cadastrar(): void {
        $this->requerFornecedor();

        $nome      = trim($_POST['nome']      ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco     = (float) str_replace(',', '.', $_POST['preco']  ?? '0');
        $estoque   = (int)   ($_POST['estoque']   ?? 0);
        $categoria = trim($_POST['categoria'] ?? '');
        $fotoUrl   = trim($_POST['foto_url']  ?? '');

        if (!$nome || $preco <= 0) {
            $this->erroJson('Nome e preço são obrigatórios.');
        }

        $produto = $this->dao->inserir(
            $_SESSION['usuario_id'],
            $nome, $descricao, $preco, $estoque, $categoria, $fotoUrl
        );

        $this->jsonSucesso(['id' => $produto->id, 'mensagem' => 'Produto cadastrado com sucesso!']);
    }

    public function atualizarEstoque(): void {
        $this->requerFornecedor();

        $produtoId    = (int)  ($_POST['produto_id']    ?? 0);
        $novoEstoque  = (int)  ($_POST['novo_estoque']  ?? 0);

        if (!$produtoId) $this->erroJson('ID do produto inválido.');

        $produto = $this->dao->buscarPorId($produtoId);
        if (!$produto || $produto->fornecedorId !== (int) $_SESSION['usuario_id']) {
            $this->erroJson('Produto não encontrado ou sem permissão.', 403);
        }

        $this->dao->atualizarEstoque($produtoId, $novoEstoque);
        $this->jsonSucesso(['mensagem' => 'Estoque atualizado!']);
    }

    public function atualizar(): void {
        $this->requerFornecedor();

        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $nome      = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $fotoUrl   = trim($_POST['foto_url'] ?? '');

        if (!$produtoId) $this->erroJson('ID do produto inválido.');
        if (!$nome) $this->erroJson('Nome é obrigatório.');

        $produto = $this->dao->buscarPorId($produtoId);
        if (!$produto || $produto->fornecedorId !== (int) $_SESSION['usuario_id']) {
            $this->erroJson('Produto não encontrado ou sem permissão.', 403);
        }

        $this->dao->atualizar($produtoId, $nome, $descricao, $produto->preco, $produto->categoria, $fotoUrl);
        $this->jsonSucesso(['mensagem' => 'Produto atualizado com sucesso!']);
    }

    public function excluir(): void {
        $this->requerFornecedor();

        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        if (!$produtoId) $this->erroJson('ID inválido.');

        $ok = $this->dao->excluir($produtoId, (int) $_SESSION['usuario_id']);
        if (!$ok) $this->erroJson('Produto não encontrado ou sem permissão.', 403);

        $this->jsonSucesso(['mensagem' => 'Produto excluído!']);
    }

    private function requerFornecedor(): void {
        if (empty($_SESSION['usuario_supplier'])) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado.']);
            exit;
        }
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
