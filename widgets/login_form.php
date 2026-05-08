<link rel="stylesheet" href="/widgets/login_register_form.css">
<link rel="stylesheet" href="../index.css">
<div class="login-container">
    <h2>Entrar:</h2>

    <?php if (!empty($_SESSION['auth_erro'])): ?>
        <p style="color:red;margin-bottom:12px;"><?= htmlspecialchars($_SESSION['auth_erro']) ?></p>
        <?php unset($_SESSION['auth_erro']); ?>
    <?php endif; ?>

    <form action="/entrar" method="POST">
        <input type="hidden" name="action" value="login">
        <input type="text"     name="email"    placeholder="E-mail" required>
        <br>
        <input type="password" name="password" placeholder="Senha"  required>
        <br>
        <button type="submit">Entrar</button>
    </form>

    <div class="register-link">
        <a href="/registrar">Não tem conta? Registrar</a>
    </div>
</div>
