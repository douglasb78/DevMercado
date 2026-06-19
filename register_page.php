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

    <p id="auth_error" style="color:red;margin-bottom:12px;"><?= !empty($_SESSION['auth_erro']) ? htmlspecialchars($_SESSION['auth_erro']) : '' ?></p>
    <?php unset($_SESSION['auth_erro']); ?>

    <form id="register-form" method="POST">
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('register-form');
    const errorEl = document.getElementById('auth_error');
    if (!form) return;
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        if (errorEl) errorEl.textContent = '';
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        try {
            const formData = new FormData(form);
            const endpoint = (form.action && form.action.length) ? form.action : window.location.pathname;
            const resp = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });
            const contentType = resp.headers.get('content-type') || '';
            const data = contentType.includes('application/json') ? await resp.json() : { success: resp.ok, message: await resp.text() };
            if (!resp.ok) {
                if (errorEl) errorEl.textContent = data?.message || 'Ocorreu um erro.';
            } else {
                if (data.redirect) {
                    window.location = data.redirect;
                } else {
                    window.location.reload();
                }
            }
        } catch (err) {
            if (errorEl) errorEl.textContent = 'Erro de rede. Tente novamente.';
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });
});
</script>
<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
