<?php
require_once __DIR__ . '/../dao/UsuarioDAO.php';

class AuthController {

    private UsuarioDAO $dao;

    public function __construct() {
        $this->dao = new UsuarioDAO();
    }

    public function login(): void {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['password'] ?? '';

        if (!$email || !$senha) {
            $this->erroRedirecionar('Preencha todos os campos.', '/entrar');
        }

        $usuario = $this->dao->buscarPorEmail($email);

        if (!$usuario || !password_verify($senha, $usuario->senha)) {
            $this->erroRedirecionar('E-mail ou senha incorretos.', '/entrar');
        }

        $this->iniciarSessao($usuario);
        header('Location: /');
        exit;
    }

    public function registrar(): void {
        $nome       = trim($_POST['nome']             ?? '');
        $email      = trim($_POST['email']            ?? '');
        $senha      = $_POST['password']              ?? '';
        $confirm    = $_POST['password_confirm']      ?? '';
        $isSupplier = isset($_POST['is_supplier']);

        if (!$nome || !$email || !$senha || !$confirm) {
            $this->erroRedirecionar('Preencha todos os campos.', '/registrar');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->erroRedirecionar('E-mail inválido.', '/registrar');
        }
        if (strlen($senha) < 6) {
            $this->erroRedirecionar('A senha deve ter pelo menos 6 caracteres.', '/registrar');
        }
        if ($senha !== $confirm) {
            $this->erroRedirecionar('As senhas não coincidem.', '/registrar');
        }
        if ($this->dao->emailJaExiste($email)) {
            $this->erroRedirecionar('Este e-mail já está cadastrado.', '/registrar');
        }

        $hash    = password_hash($senha, PASSWORD_BCRYPT);
        $usuario = $this->dao->inserir($nome, $email, $hash, $isSupplier);

        $this->iniciarSessao($usuario);
        header('Location: /');
        exit;
    }

    public function logout(): void {
        session_destroy();
        header('Location: /');
        exit;
    }

    public function atualizarPerfil(): void {
        if (empty($_SESSION['usuario_id'])) {
            $_SESSION['erro_perfil'] = 'Faça login para atualizar seu perfil.';
            header('Location: /entrar');
            exit;
        }

        $id = $_SESSION['usuario_id'];
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $cartaocredito = trim($_POST['cartaocredito'] ?? '');
        $isSupplier = isset($_POST['is_supplier']);
        $senha = $_POST['senha'] ?? '';
        $senhaConfirm = $_POST['senha_confirm'] ?? '';

        if (!$nome) {
            $_SESSION['erro_perfil'] = 'Nome é obrigatório.';
            header('Location: /perfil');
            exit;
        }

        if (!$email) {
            $_SESSION['erro_perfil'] = 'E-mail é obrigatório.';
            header('Location: /perfil');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['erro_perfil'] = 'E-mail inválido.';
            header('Location: /perfil');
            exit;
        }

        $usuarioAtual = $this->dao->buscarPorId($id);
        if (!$usuarioAtual) {
            $_SESSION['erro_perfil'] = 'Usuário não encontrado.';
            header('Location: /perfil');
            exit;
        }

        if ($email !== $usuarioAtual->email && $this->dao->emailJaExiste($email)) {
            $_SESSION['erro_perfil'] = 'Este e-mail já está cadastrado.';
            header('Location: /perfil');
            exit;
        }

        $senhaHash = null;
        if ($senha) {
            if (strlen($senha) < 6) {
                $_SESSION['erro_perfil'] = 'A senha deve ter pelo menos 6 caracteres.';
                header('Location: /perfil');
                exit;
            }
            if ($senha !== $senhaConfirm) {
                $_SESSION['erro_perfil'] = 'As senhas não coincidem.';
                header('Location: /perfil');
                exit;
            }
            $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
        }

        $usuarioAtualizado = $this->dao->atualizar(
            $id,
            $email,
            $senhaHash,
            $telefone ?: null,
            $cartaocredito ?: null,
            $nome,
            $isSupplier
        );

        $_SESSION['usuario_nome'] = $usuarioAtualizado->nome;
        $_SESSION['usuario_supplier'] = $usuarioAtualizado->isSupplier;
        $_SESSION['sucesso_perfil'] = 'Perfil atualizado com sucesso!';
        header('Location: /perfil');
        exit;
    }

    private function iniciarSessao(object $usuario): void {
        session_regenerate_id(true);
        $_SESSION['usuario_id']       = $usuario->id;
        $_SESSION['usuario_nome']     = $usuario->nome;
        $_SESSION['usuario_supplier'] = $usuario->isSupplier;
    }

    private function erroRedirecionar(string $mensagem, string $destino): never {
        $_SESSION['auth_erro'] = $mensagem;
        header("Location: $destino");
        exit;
    }
}
