<?php
require_once __DIR__ . '/../dao/CarrinhoDAO.php';

class CarrinhoController {

    private CarrinhoDAO $dao;

    public function __construct() {
        $this->dao = new CarrinhoDAO();
    }

    public function adicionar(): void {
        $this->requerLogin();

        $produtoId  = (int) ($_POST['produto_id']  ?? 0);
        $quantidade = (int) ($_POST['quantidade']  ?? 1);

        if (!$produtoId || $quantidade < 1) {
            $this->erroJson('Dados inválidos.');
        }

        $this->dao->adicionarOuIncrementar(
            (int) $_SESSION['usuario_id'],
            $produtoId,
            $quantidade
        );

        $total = $this->dao->contar((int) $_SESSION['usuario_id']);
        $this->jsonSucesso(['mensagem' => 'Adicionado ao carrinho!', 'total_itens' => $total]);
    }

    public function atualizar(): void {
        $this->requerLogin();

        $produtoId  = (int) ($_POST['produto_id']  ?? 0);
        $quantidade = (int) ($_POST['quantidade']  ?? 0);

        $this->dao->atualizarQuantidade(
            (int) $_SESSION['usuario_id'],
            $produtoId,
            $quantidade
        );

        $this->jsonSucesso(['mensagem' => 'Carrinho atualizado.']);
    }

    public function remover(): void {
        $this->requerLogin();

        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $this->dao->remover((int) $_SESSION['usuario_id'], $produtoId);
        $this->jsonSucesso(['mensagem' => 'Item removido.']);
    }

    private function requerLogin(): void {
        if (empty($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['erro' => 'Não autenticado.']);
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
