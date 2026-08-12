<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($title) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">🏛️</div>
                <h1 class="login-title">Sistema de Assembleias</h1>
                <p class="login-subtitle">Condomínios Residenciais</p>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= sanitize($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= base_url('?route=auth/login') ?>" class="login-form">
                <div class="form-group">
                    <label for="cpf" class="form-label">Digite seu CPF</label>
                    <input
                        type="text"
                        id="cpf"
                        name="cpf"
                        class="form-input input-lg"
                        placeholder="000.000.000-00"
                        required
                        autocomplete="username"
                        maxlength="14"
                    >
                </div>

                <div class="form-group" id="senha-group" style="display:none;">
                    <label for="senha" class="form-label">Senha (Administrador)</label>
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        class="form-input input-lg"
                        placeholder="Digite sua senha"
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Acessar Sistema
                </button>
            </form>

            <div class="login-footer">
                <p>Sistema projetado pelo <strong>Eng. de Software Cristiam Matos</strong></p>
            </div>
        </div>
    </div>

    <script src="<?= base_url('assets/js/app.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cpfInput = document.getElementById('cpf');
            const senhaGroup = document.getElementById('senha-group');

            aplicarMascaraCpf(cpfInput);

            cpfInput.addEventListener('blur', function() {
                const cpfLimpo = this.value.replace(/\D/g, '');
                if (cpfLimpo === '00000000000') {
                    senhaGroup.style.display = 'block';
                } else {
                    senhaGroup.style.display = 'none';
                    document.getElementById('senha').value = '';
                }
            });
        });
    </script>
</body>
</html>
