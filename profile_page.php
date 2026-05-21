<link rel="stylesheet" href="/css/profile_page.css">

<?php
session_start();
require_once __DIR__ . '/include_all.php';

// Processar atualização de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'atualizar_perfil') {
    $auth = new AuthController();
    $auth->atualizarPerfil();
}

$usuarioId = $_SESSION['usuario_id'] ?? null;
$dao = new UsuarioDAO();
$usuario = $dao->buscarPorId($usuarioId);

ob_start();
?>
<link rel="stylesheet" href="/css/profile_page.css">

<div class="profile-container">
    <h2>Meu Perfil</h2>

    <?php if (!empty($_SESSION['sucesso_perfil'])): ?>
        <p style="color:green;margin-bottom:12px;"><?= htmlspecialchars($_SESSION['sucesso_perfil']) ?></p>
        <?php unset($_SESSION['sucesso_perfil']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['erro_perfil'])): ?>
        <p style="color:red;margin-bottom:12px;"><?= htmlspecialchars($_SESSION['erro_perfil']) ?></p>
        <?php unset($_SESSION['erro_perfil']); ?>
    <?php endif; ?>

    <form action="/profile_page.php" method="POST" class="profile-form">
        <input type="hidden" name="action" value="atualizar_perfil">

        <div class="form-group">
            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($usuario->nome) ?>" required>
        </div>

        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($usuario->email) ?>" required>
        </div>

        <div class="form-group">
            <label for="telefone">Telefone:</label>
            <input type="tel" id="telefone" name="telefone" value="<?= htmlspecialchars($usuario->telefone ?? '') ?>" placeholder="(11) 98765-4321">
        </div>

        <div class="form-group">
            <label for="cartaocredito">Cartão de Crédito:</label>
            <input type="text" id="cartaocredito" name="cartaocredito" value="<?= htmlspecialchars($usuario->cartaocredito ?? '') ?>" placeholder="1234 5678 9012 3456">
        </div>

        <div class="form-group checkbox">
                <input type="checkbox" id="is_supplier" name="is_supplier" <?= $usuario->isSupplier ? 'checked' : '' ?>>
                <label for="is_supplier">Quero vender produtos no site</label>
        </div>

        <div class="form-group">
            <label for="senha">Nova Senha (deixe em branco para manter a atual):</label>
            <input type="password" id="senha" name="senha" placeholder="Deixe em branco para não alterar">
        </div>

        <div class="form-group">
            <label for="senha_confirm">Confirmar Nova Senha:</label>
            <input type="password" id="senha_confirm" name="senha_confirm" placeholder="Confirme a nova senha">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-salvar">Salvar Alterações</button>
            <a href="/" class="btn-cancelar">Cancelar</a>
        </div>
    </form>

    <div class="profile-info">
        <p><strong>Membro desde:</strong> <?= date('d/m/Y', strtotime($usuario->criadoEm)) ?></p>
    </div>
</div>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>

