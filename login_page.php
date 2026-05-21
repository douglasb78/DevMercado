<?php
session_start();
require_once __DIR__ . '/include_all.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $auth = new AuthController();
    $auth->login();
}

ob_start();
?>
<link rel="stylesheet" href="/css/login_register_page.css">
<div class="login-container">
    <h2>Entrar:</h2>

    <?php if (!empty($_SESSION['auth_erro'])): ?>
        <p style="color:red;margin-bottom:12px;"><?= htmlspecialchars($_SESSION['auth_erro']) ?></p>
        <?php unset($_SESSION['auth_erro']); ?>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="login">
        <input type="text"     name="email"    placeholder="E-mail" required>
        <br>
        <input type="password" name="password" placeholder="Senha"  required>
        <br>
        <button type="submit">Entrar</button>
    </form>

    <div class="register-link">
        <a href="register_page.php">Não tem conta? Registrar</a>
    </div>
</div>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
