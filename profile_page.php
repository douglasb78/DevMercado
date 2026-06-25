<?php
session_start();
require_once __DIR__ . '/include_all.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $auth = new AuthController();
    if ($_POST['action'] === 'atualizar_perfil') {
        $auth->atualizarPerfil();
    } elseif ($_POST['action'] === 'alterar_senha') {
        $auth->alterarSenha();
    } elseif ($_POST['action'] === 'atualizar_cartao') {
        $auth->atualizarCartao();
    }
}

if (empty($_SESSION['usuario_id'])) {
    $_SESSION['erro_perfil'] = 'Faça login para acessar seu perfil.';
    header('Location: login_page.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$dao = new UsuarioDAO();
$usuario = $dao->buscarPorId($usuarioId);

if (!$usuario) {
    session_destroy();
    header('Location: login_page.php');
    exit;
}

$inicial   = mb_strtoupper(mb_substr($usuario->nome, 0, 1));
$tipoConta = $usuario->isAdmin ? 'Administrador' : ($usuario->isSupplier ? 'Vendedor' : 'Comprador');

$cpfFmt = strlen($usuario->cpf) === 11
    ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $usuario->cpf)
    : '';

$cartaoDigitos   = preg_replace('/\D/', '', $usuario->cartaocredito ?? '');
$cartaoMascarado = strlen($cartaoDigitos) >= 4 ? '•••• ' . substr($cartaoDigitos, -4) : '';

$temEnderecoEstruturado = $usuario->enderecoLogradouro !== '';
$enderecoLegado = trim($usuario->endereco ?? '');

ob_start();
?>
<link rel="stylesheet" href="/css/pages/profile.css?v=2">

<div class="profile-container">

    <!-- Cabeçalho da conta -->
    <header class="perfil-header">
        <div class="perfil-header-info">
            <h2>Minha conta</h2>
            <p class="perfil-nome">Nome: <?= htmlspecialchars($usuario->nome) ?></p>
            <p class="perfil-email">E-mail: <?= htmlspecialchars($usuario->email) ?></p>
            <p class="perfil-badges">
                <span class="perfil-badge">Tipo de conta: <?= htmlspecialchars($tipoConta) ?></span>
            </p>
        </div>
    </header>

    <div class="perfil-grid">

        <!-- ════════ COLUNA ESQUERDA: dados do usuário e endereço ════════ -->
        <div class="perfil-col perfil-col-esq">

            <?php if (!empty($_SESSION['sucesso_perfil'])): ?>
                <p class="msg-ok"><?= htmlspecialchars($_SESSION['sucesso_perfil']) ?></p>
                <?php unset($_SESSION['sucesso_perfil']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['erro_perfil'])): ?>
                <p class="msg-erro"><?= htmlspecialchars($_SESSION['erro_perfil']) ?></p>
                <?php unset($_SESSION['erro_perfil']); ?>
            <?php endif; ?>

            <form action="/profile_page.php" method="POST" class="profile-form">
                <input type="hidden" name="action" value="atualizar_perfil">

                <fieldset class="perfil-secao">
                    <legend>Dados pessoais</legend>

                    <div class="form-group">
                        <label for="nome">Nome completo</label>
                        <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($usuario->nome) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($usuario->email) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cpf">CPF</label>
                        <input type="text" id="cpf" name="cpf" value="<?= htmlspecialchars($cpfFmt) ?>" placeholder="000.000.000-00" inputmode="numeric" maxlength="14">
                    </div>

                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="tel" id="telefone" name="telefone" value="<?= htmlspecialchars($usuario->telefone ?? '') ?>" placeholder="(11) 98765-4321" maxlength="15">
                    </div>
                </fieldset>

                <fieldset class="perfil-secao">
                    <legend>Endereço</legend>

                    <?php if (!$temEnderecoEstruturado && $enderecoLegado !== ''): ?>
                        <p class="perfil-hint">Endereço atual: <strong><?= htmlspecialchars($enderecoLegado) ?></strong>. Preencha os campos abaixo para atualizá-lo no novo formato.</p>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="cep">CEP</label>
                        <input type="text" id="cep" name="cep" value="<?= htmlspecialchars($usuario->cep) ?>" placeholder="00000-000" inputmode="numeric" maxlength="9">
                        <small class="perfil-hint">Digite o CEP para preencher o endereço automaticamente.</small>
                    </div>

                    <div class="form-group">
                        <label for="endereco_logradouro">Rua / Logradouro</label>
                        <input type="text" id="endereco_logradouro" name="endereco_logradouro" value="<?= htmlspecialchars($usuario->enderecoLogradouro) ?>">
                    </div>

                    <div class="form-group">
                        <label for="endereco_numero">Número</label>
                        <input type="text" id="endereco_numero" name="endereco_numero" value="<?= htmlspecialchars($usuario->enderecoNumero) ?>" placeholder="Ex.: 123">
                    </div>

                    <div class="form-group">
                        <label for="endereco_complemento">Complemento</label>
                        <input type="text" id="endereco_complemento" name="endereco_complemento" value="<?= htmlspecialchars($usuario->enderecoComplemento) ?>" placeholder="Apto, bloco, referência (opcional)">
                    </div>

                    <div class="form-group">
                        <label for="endereco_bairro">Bairro</label>
                        <input type="text" id="endereco_bairro" name="endereco_bairro" value="<?= htmlspecialchars($usuario->enderecoBairro) ?>">
                    </div>

                    <div class="form-group">
                        <label for="endereco_cidade">Cidade</label>
                        <input type="text" id="endereco_cidade" name="endereco_cidade" value="<?= htmlspecialchars($usuario->enderecoCidade) ?>">
                    </div>

                    <div class="form-group">
                        <label for="endereco_uf">UF</label>
                        <input type="text" id="endereco_uf" name="endereco_uf" value="<?= htmlspecialchars($usuario->enderecoUf) ?>" placeholder="SP" maxlength="2">
                    </div>
                </fieldset>

                <fieldset class="perfil-secao">
                    <legend>Preferências</legend>
                    <?php if ($usuario->isSupplier): ?>
                        <p class="perfil-hint">✓ Você é um vendedor. <a href="/manage_page.php">Ir para a Área do vendedor</a>.</p>
                        <input type="hidden" name="is_supplier" value="1">
                    <?php else: ?>
                        <div class="form-group checkbox">
                            <input type="checkbox" id="is_supplier" name="is_supplier" value="1">
                            <label for="is_supplier">Quero vender produtos no DevMercado</label>
                        </div>
                        <small class="perfil-hint">Ao ativar, você ganha acesso à Área do vendedor. (Não é possível desativar depois.)</small>
                    <?php endif; ?>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn-salvar">Salvar alterações</button>
                    <a href="/" class="btn-cancelar">Cancelar</a>
                </div>
            </form>
        </div>

        <!-- ════════ COLUNA DIREITA: senha e cartão ════════ -->
        <div class="perfil-col perfil-col-dir">

            <!-- Segurança: alterar senha -->
            <div>
                <?php if (!empty($_SESSION['sucesso_senha'])): ?>
                    <p class="msg-ok"><?= htmlspecialchars($_SESSION['sucesso_senha']) ?></p>
                    <?php unset($_SESSION['sucesso_senha']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['erro_senha'])): ?>
                    <p class="msg-erro"><?= htmlspecialchars($_SESSION['erro_senha']) ?></p>
                    <?php unset($_SESSION['erro_senha']); ?>
                <?php endif; ?>

                <form action="/profile_page.php" method="POST" class="profile-form">
                    <input type="hidden" name="action" value="alterar_senha">

                    <fieldset class="perfil-secao">
                        <legend>Alterar senha</legend>

                        <div class="form-group">
                            <label for="senha_atual">Senha atual</label>
                            <input type="password" id="senha_atual" name="senha_atual" autocomplete="current-password">
                            <small class="perfil-hint">Confirme sua senha atual para autorizar a alteração.</small>
                        </div>

                        <div class="form-group">
                            <label for="senha">Nova senha</label>
                            <input type="password" id="senha" name="senha" autocomplete="new-password">
                            <small class="perfil-hint">Mínimo de 6 caracteres.</small>
                        </div>

                        <div class="form-group">
                            <label for="senha_confirm">Confirmar nova senha</label>
                            <input type="password" id="senha_confirm" name="senha_confirm" autocomplete="new-password">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-salvar">Alterar senha</button>
                        </div>
                    </fieldset>
                </form>
            </div>

            <!-- Meios de pagamento: cartão -->
            <div>
                <?php if (!empty($_SESSION['sucesso_cartao'])): ?>
                    <p class="msg-ok"><?= htmlspecialchars($_SESSION['sucesso_cartao']) ?></p>
                    <?php unset($_SESSION['sucesso_cartao']); ?>
                <?php endif; ?>
                <?php if (!empty($_SESSION['erro_cartao'])): ?>
                    <p class="msg-erro"><?= htmlspecialchars($_SESSION['erro_cartao']) ?></p>
                    <?php unset($_SESSION['erro_cartao']); ?>
                <?php endif; ?>

                <form action="/profile_page.php" method="POST" class="profile-form">
                    <input type="hidden" name="action" value="atualizar_cartao">

                    <fieldset class="perfil-secao">
                        <legend>Meios de pagamento</legend>

                        <p class="perfil-cartao-atual">
                            <?php if ($cartaoMascarado !== ''): ?>
                                Cartão cadastrado: <strong><?= htmlspecialchars($cartaoMascarado) ?></strong>
                            <?php else: ?>
                                Nenhum cartão cadastrado.
                            <?php endif; ?>
                        </p>

                        <div class="form-group">
                            <label for="cartaocredito">Adicionar / atualizar cartão</label>
                            <input type="text" id="cartaocredito" name="cartaocredito" value="" placeholder="0000 0000 0000 0000" inputmode="numeric" autocomplete="off" maxlength="19">
                            <small class="perfil-hint">Por segurança, guardamos apenas os 4 últimos dígitos.</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-salvar">Salvar cartão</button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
// Autopreenchimento de endereço pelo CEP (ViaCEP — API pública gratuita).
const cepInput = document.getElementById('cep');
if (cepInput) {
    cepInput.addEventListener('blur', () => {
        const cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(r => r.json())
            .then(d => {
                if (d.erro) return;
                const set = (id, v) => { const el = document.getElementById(id); if (el && v) el.value = v; };
                set('endereco_logradouro', d.logradouro);
                set('endereco_bairro', d.bairro);
                set('endereco_cidade', d.localidade);
                set('endereco_uf', d.uf);
                document.getElementById('endereco_numero').focus();
            })
            .catch(() => {});
    });
}

// Máscaras simples (apenas visuais; o backend normaliza os dígitos).
function mascara(el, fn) {
    if (!el) return;
    el.addEventListener('input', () => { el.value = fn(el.value); });
}
mascara(document.getElementById('cpf'), v => {
    v = v.replace(/\D/g, '').slice(0, 11);
    return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
});
mascara(document.getElementById('cep'), v => {
    v = v.replace(/\D/g, '').slice(0, 8);
    return v.replace(/(\d{5})(\d)/, '$1-$2');
});
mascara(document.getElementById('telefone'), v => {
    v = v.replace(/\D/g, '').slice(0, 11);
    if (v.length <= 10) return v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
    return v.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
});
mascara(document.getElementById('cartaocredito'), v => {
    return v.replace(/\D/g, '').slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1 ');
});
</script>

<?php
$website_content = ob_get_clean();

include __DIR__ . '/template/index_template.php';
?>
