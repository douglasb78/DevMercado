<?php
session_start();
require_once __DIR__ . '/include_all.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'registrar') {
    $auth = new AuthController();
    $auth->registrar();
}

ob_start();
?>
<link rel="stylesheet" href="/css/login_register_page.css">
<div class="login-container">
    <h2>Realizar cadastro:</h2>

    <?php if (!empty($_SESSION['auth_erro'])): ?>
        <p style="color:red;margin-bottom:12px;"><?= htmlspecialchars($_SESSION['auth_erro']) ?></p>
        <?php unset($_SESSION['auth_erro']); ?>
    <?php endif; ?>

    <form action="/register_page.php" method="POST">
        <input type="hidden" name="action" value="registrar">
        <input type="text" name="nome" placeholder="Nome completo" required>
        <br>
        <input type="text" name="email" placeholder="E-mail" required>
        <br>
        <input type="text" name="endereco" placeholder="Endereço">
        <br>
        <input type="password" name="password" placeholder="Senha" required>
        <br>
        <input type="password" name="password_confirm" placeholder="Confirme a Senha" required>
        <br>
        <input type="checkbox" name="is_supplier" id="is_supplier">
        <label for="is_supplier">Quero vender produtos no site.</label>
        <br><br>
        <button type="submit">Criar conta</button>
    </form>
</div>
<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
