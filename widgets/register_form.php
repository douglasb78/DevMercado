<link rel="stylesheet" href="/widgets/login_register_form.css">
<link rel="stylesheet" href="../index.css">
<div class="login-container">
    <h2>Realizar cadastro:</h2>

    <?php if (!empty($_SESSION['auth_erro'])): ?>
        <p style="color:red;margin-bottom:12px;"><?= htmlspecialchars($_SESSION['auth_erro']) ?></p>
        <?php unset($_SESSION['auth_erro']); ?>
    <?php endif; ?>

    <form action="/registrar" method="POST">
        <input type="hidden" name="action" value="registrar">
        <input type="text" name="nome" placeholder="Nome completo" required>
        <br>
        <input type="text" name="email" placeholder="E-mail" required>
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
