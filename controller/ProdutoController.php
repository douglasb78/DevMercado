<?php
require_once __DIR__ . '/../dao/ProdutoDAO.php';

class ProdutoController {

    public const CATEGORIAS_PERMITIDAS = [
        'Placa de Vídeo',
        'Processador',
        'Placa-mãe',
        'Memória RAM',
        'Armazenamento',
        'Fonte',
        'Gabinete',
        'Refrigeração',
        'Periféricos',
        'Outros',
    ];

    private ProdutoDAO $dao;

    public function __construct() {
        $this->dao = new ProdutoDAO();
    }

    public function cadastrar(): void {
        $this->requerFornecedor();

        $nome      = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $preco     = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
        $estoque   = (int) ($_POST['estoque'] ?? 0);
        $categoria = trim($_POST['categoria'] ?? '');
        $fotoUrl   = $this->salvarUpload('foto_arquivo') ?: trim($_POST['foto_url'] ?? '');

        if (!$nome || $preco <= 0) {
            $this->erroJson('Nome e preço são obrigatórios.');
        }
        if (!$this->categoriaValida($categoria)) {
            $this->erroJson('Categoria inválida.');
        }

        $produto = $this->dao->inserir(
            (int) $_SESSION['usuario_id'],
            $nome,
            $descricao,
            $preco,
            $estoque,
            $categoria,
            $fotoUrl
        );

        $this->jsonSucesso(['id' => $produto->id, 'mensagem' => 'Produto cadastrado com sucesso!']);
    }

    public function atualizarEstoque(): void {
        $this->requerFornecedor();

        $produtoId   = (int) ($_POST['produto_id'] ?? 0);
        $novoEstoque = (int) ($_POST['novo_estoque'] ?? 0);

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
        $fotoUrl   = $this->salvarUpload('foto_arquivo') ?: trim($_POST['foto_url'] ?? '');

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

        $ok = $this->dao->softDelete($produtoId, (int) $_SESSION['usuario_id']);
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

    private function categoriaValida(string $categoria): bool {
        return in_array($categoria, self::CATEGORIAS_PERMITIDAS, true);
    }

    private function salvarUpload(string $campo): ?string {
        if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            $this->erroJson('Erro ao enviar imagem.');
        }

        $tmp = $_FILES[$campo]['tmp_name'];
        $info = getimagesize($tmp);
        if (!$info) {
            $this->erroJson('O arquivo enviado precisa ser uma imagem.');
        }

        $extensoes = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF => 'gif',
        ];
        $ext = $extensoes[$info[2]] ?? null;
        if (!$ext) {
            $this->erroJson('Formato de imagem não permitido.');
        }

        $pasta = dirname(__DIR__) . '/upload_media';
        if (!is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }

        $nome = 'produto_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destino = $pasta . '/' . $nome;
        if (!move_uploaded_file($tmp, $destino)) {
            $this->erroJson('Não foi possível salvar a imagem.');
        }

        return '/upload_media/' . $nome;
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
