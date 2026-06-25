<?php
require_once __DIR__ . '/../dao/UsuarioDAO.php';

class AuthController {

    private UsuarioDAO $dao;

    private function isAjax(): bool {
        $xRequested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strcasecmp($xRequested, 'XMLHttpRequest') === 0 || stripos($accept, 'application/json') !== false;
    }

    public function __construct() {
        $this->dao = new UsuarioDAO();
    }

    public function login(): void {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['password'] ?? '';

        if (!$email || !$senha) {
            $this->erroRedirecionar('Preencha todos os campos.', 'login_page.php');
        }

        $usuario = $this->dao->buscarPorEmail($email);

        if (!$usuario || !password_verify($senha, $usuario->senha)) {
            $this->erroRedirecionar('E-mail ou senha incorretos.', 'login_page.php');
        }

        $this->iniciarSessao($usuario);
        (new CarrinhoController())->sincronizarUsuario($usuario->id);
        $next = trim($_POST['next'] ?? '');
        $destino = preg_match('/^[a-zA-Z0-9_\/.-]+(\?[a-zA-Z0-9_=&%-]+)?$/', $next) ? $next : 'index.php';
        if ($this->isAjax()) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect' => $destino]);
            exit;
        }
        header('Location: ' . $destino);
        exit;
    }

    public function registrar(): void {
        $nome       = trim($_POST['nome']             ?? '');
        $email      = trim($_POST['email']            ?? '');
        $senha      = $_POST['password']              ?? '';
        $confirm    = $_POST['password_confirm']      ?? '';
        $isSupplier = isset($_POST['is_supplier']);
        $endereco   = trim($_POST['endereco'] ?? '');

        if (!$nome || !$email || !$senha || !$confirm) {
            $this->erroRedirecionar('Preencha todos os campos.', 'register_page.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->erroRedirecionar('E-mail inválido.', 'register_page.php');
        }
        if (strlen($senha) < 6) {
            $this->erroRedirecionar('A senha deve ter pelo menos 6 caracteres.', 'register_page.php');
        }
        if ($senha !== $confirm) {
            $this->erroRedirecionar('As senhas não coincidem.', 'register_page.php');
        }
        if ($this->dao->emailJaExiste($email)) {
            $this->erroRedirecionar('Este e-mail já está cadastrado.', 'register_page.php');
        }

        $hash    = password_hash($senha, PASSWORD_BCRYPT);
        $usuario = $this->dao->inserir($nome, $email, $hash, $isSupplier, null, null, $endereco);

        $this->iniciarSessao($usuario);
        (new CarrinhoController())->sincronizarUsuario($usuario->id);
        if ($this->isAjax()) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect' => 'index.php']);
            exit;
        }
        header('Location: index.php');
        exit;
    }

    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        setcookie('devmercado_carrinho', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']);
        unset($_COOKIE['devmercado_carrinho']);
        header('Location: index.php');
        exit;
    }

    public function atualizarPerfil(): void {
        if (empty($_SESSION['usuario_id'])) {
            $_SESSION['erro_perfil'] = 'Faça login para atualizar seu perfil.';
            header('Location: login_page.php');
            exit;
        }

        $id = (int) $_SESSION['usuario_id'];
        $usuarioAtual = $this->dao->buscarPorId($id);
        if (!$usuarioAtual) {
            $this->erroPerfil('Usuário não encontrado.');
        }

        $nome       = trim($_POST['nome'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $telefone   = trim($_POST['telefone'] ?? '');
        $isSupplier = isset($_POST['is_supplier']);

        $cpf         = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
        $cep         = trim($_POST['cep'] ?? '');
        $logradouro  = trim($_POST['endereco_logradouro'] ?? '');
        $numero      = trim($_POST['endereco_numero'] ?? '');
        $complemento = trim($_POST['endereco_complemento'] ?? '');
        $bairro      = trim($_POST['endereco_bairro'] ?? '');
        $cidade      = trim($_POST['endereco_cidade'] ?? '');
        $uf          = strtoupper(trim($_POST['endereco_uf'] ?? ''));
        $cartaoDigitos = preg_replace('/\D/', '', $_POST['cartaocredito'] ?? '');

        if (!$nome)  $this->erroPerfil('Nome é obrigatório.');
        if (!$email) $this->erroPerfil('E-mail é obrigatório.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->erroPerfil('E-mail inválido.');
        if ($cpf !== '' && !self::cpfValido($cpf))        $this->erroPerfil('CPF inválido.');
        if ($uf !== '' && strlen($uf) !== 2)              $this->erroPerfil('UF deve ter 2 letras (ex.: SP).');

        $isSupplier = $usuarioAtual->isSupplier || $isSupplier;

        if ($email !== $usuarioAtual->email && $this->dao->emailJaExiste($email)) {
            $this->erroPerfil('Este e-mail já está cadastrado.');
        }

        $endereco = new Endereco([
            'endereco_logradouro'  => $logradouro,
            'endereco_numero'      => $numero,
            'endereco_complemento' => $complemento,
            'endereco_bairro'      => $bairro,
            'endereco_cidade'      => $cidade,
            'endereco_uf'          => $uf,
            'cep'                  => $cep,
        ]);
        $enderecoComposto = $endereco->formatado() ?: null;

        $cartaoArmazenar = null;
        if ($cartaoDigitos !== '') {
            if (strlen($cartaoDigitos) < 4) $this->erroPerfil('Número de cartão inválido.');
            $cartaoArmazenar = '•••• ' . substr($cartaoDigitos, -4);
        }

        $usuarioAtualizado = $this->dao->atualizar(
            $id,
            $email,
            nome: $nome,
            telefone: $telefone ?: null,
            isSupplier: $isSupplier,
            cpf: $cpf,
            cep: $cep,
            enderecoLogradouro: $logradouro,
            enderecoNumero: $numero,
            enderecoComplemento: $complemento,
            enderecoBairro: $bairro,
            enderecoCidade: $cidade,
            enderecoUf: $uf,
            endereco: $enderecoComposto,
            cartaocredito: $cartaoArmazenar
        );

        $_SESSION['usuario_nome']     = $usuarioAtualizado->nome;
        $_SESSION['usuario_supplier'] = $usuarioAtualizado->isSupplier;
        $_SESSION['usuario_admin']    = $usuarioAtualizado->isAdmin;
        $_SESSION['sucesso_perfil']   = 'Dados atualizados com sucesso!';
        header('Location: profile_page.php');
        exit;
    }

    public function alterarSenha(): void {
        if (empty($_SESSION['usuario_id'])) {
            $_SESSION['erro_senha'] = 'Faça login para alterar a senha.';
            header('Location: login_page.php');
            exit;
        }

        $id = (int) $_SESSION['usuario_id'];
        $usuario = $this->dao->buscarPorId($id);
        if (!$usuario) {
            $this->erroPerfil('Usuário não encontrado.', 'erro_senha');
        }

        $atual   = $_POST['senha_atual'] ?? '';
        $nova    = $_POST['senha'] ?? '';
        $confirm = $_POST['senha_confirm'] ?? '';

        if (!$atual || !$nova || !$confirm)              $this->erroPerfil('Preencha todos os campos de senha.', 'erro_senha');
        if (!password_verify($atual, $usuario->senha))   $this->erroPerfil('Senha atual incorreta.', 'erro_senha');
        if (strlen($nova) < 6)                           $this->erroPerfil('A nova senha deve ter pelo menos 6 caracteres.', 'erro_senha');
        if ($nova !== $confirm)                          $this->erroPerfil('A confirmação não coincide com a nova senha.', 'erro_senha');
        if (password_verify($nova, $usuario->senha))     $this->erroPerfil('A nova senha deve ser diferente da atual.', 'erro_senha');

        $this->dao->atualizar($id, $usuario->email, senhaHash: password_hash($nova, PASSWORD_BCRYPT));
        $_SESSION['sucesso_senha'] = 'Senha alterada com sucesso!';
        header('Location: profile_page.php');
        exit;
    }

    public function atualizarCartao(): void {
        if (empty($_SESSION['usuario_id'])) {
            $_SESSION['erro_cartao'] = 'Faça login para atualizar o cartão.';
            header('Location: login_page.php');
            exit;
        }

        $id = (int) $_SESSION['usuario_id'];
        $usuario = $this->dao->buscarPorId($id);
        if (!$usuario) {
            $this->erroPerfil('Usuário não encontrado.', 'erro_cartao');
        }

        $digitos = preg_replace('/\D/', '', $_POST['cartaocredito'] ?? '');
        if ($digitos === '')       $this->erroPerfil('Informe o número do cartão.', 'erro_cartao');
        if (strlen($digitos) < 4)  $this->erroPerfil('Número de cartão inválido.', 'erro_cartao');

        $this->dao->atualizar($id, $usuario->email, cartaocredito: '•••• ' . substr($digitos, -4));
        $_SESSION['sucesso_cartao'] = 'Cartão atualizado com sucesso!';
        header('Location: profile_page.php');
        exit;
    }

    private function erroPerfil(string $mensagem, string $chave = 'erro_perfil'): never {
        $_SESSION[$chave] = $mensagem;
        header('Location: profile_page.php');
        exit;
    }

    private static function cpfValido(string $cpf): bool {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }
        return true;
    }

    private function iniciarSessao(object $usuario): void {
        session_regenerate_id(true);
        $_SESSION['usuario_id']       = $usuario->id;
        $_SESSION['usuario_nome']     = $usuario->nome;
        $_SESSION['usuario_supplier'] = $usuario->isSupplier;
        $_SESSION['usuario_admin']    = $usuario->isAdmin;
    }

    private function erroRedirecionar(string $mensagem, string $destino): never {
        if ($this->isAjax()) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $mensagem]);
            exit;
        }
        $_SESSION['auth_erro'] = $mensagem;
        header("Location: $destino");
        exit;
    }
}
