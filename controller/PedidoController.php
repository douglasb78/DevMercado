<?php
require_once __DIR__ . '/../dao/PedidoDAO.php';
require_once __DIR__ . '/../dao/CarrinhoDAO.php';

class PedidoController {

    private const STATUS_PERMITIDOS = ['preparacao', 'transito', 'saiu', 'entregue', 'cancelado'];

    private PedidoDAO $pedidoDao;
    private CarrinhoDAO $carrinhoDao;

    public function __construct() {
        $this->pedidoDao = new PedidoDAO();
        $this->carrinhoDao = new CarrinhoDAO();
    }

    public function finalizar(): void {
        $this->requerLogin();

        $uid = (int) $_SESSION['usuario_id'];
        $carrinhoController = new CarrinhoController();
        $carrinhoController->sincronizarUsuario($uid);
        $itens = $carrinhoController->itens();

        if (empty($itens)) {
            $this->erroJson('Seu carrinho está vazio.');
        }

        try {
            $pedido = $this->pedidoDao->criarDesdoCarrinho($uid, $itens);
            $carrinhoController->limparCookie();
            $this->jsonSucesso([
                'mensagem' => 'Pedido realizado com sucesso!',
                'pedido_id' => $pedido->id,
            ]);
        } catch (EnderecoFaltandoException $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['erro' => $e->getMessage(), 'endereco_faltando' => true]);
            exit;
        } catch (RuntimeException $e) {
            $this->erroJson($e->getMessage());
        }
    }

    public function atualizarStatus(): void {
        $this->requerFornecedor();

        $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        $dataEstimada = $_POST['data_estimada'] ?? null;

        if (!in_array($status, self::STATUS_PERMITIDOS, true)) {
            $this->erroJson('Status inválido.');
        }

        $ok = $this->pedidoDao->atualizarStatus(
            $pedidoId,
            $status,
            $dataEstimada ?: null,
            (int) $_SESSION['usuario_id']
        );

        if (!$ok) {
            $this->erroJson('Pedido não encontrado ou sem permissão.', 403);
        }

        $this->jsonSucesso(['mensagem' => 'Status atualizado!']);
    }

    private function requerLogin(): void {
        if (empty($_SESSION['usuario_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Entre para finalizar a compra.', 'login' => true]);
            exit;
        }
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
