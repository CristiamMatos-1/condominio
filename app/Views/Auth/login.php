<?php
/**
 * View: Auth/login — IDENTIDADE VISUAL CONINFOMS
 *
 * - Paleta: azul corporativo + dourado (acessibilidade idosos)
 * - Mobile First: 1 coluna, inputs 100%, botões com 44px+ de altura
 * - Zero CSS inline: tudo reutiliza classes .login-ci, .card-ci, .input-ci, .btn-ci
 * - Assinatura do Engenheiro no footer da página e do card
 * - Campo senha aparece dinamicamente apenas se CPF = 000.000.000-00 (admin)
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1E40AF">
    <title><?= sanitize($title ?? 'Acessar Sistema | Assembleias Condominiais') ?></title>
    <meta name="description" content="Sistema de Eleição e Gestão de Assembleias para Condomínios - CONINFOMS Soluções em Tecnologia">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="stylesheet" href="<?= sanitize(base_url('assets/css/style.css')) ?>">
</head>
<body class="login-ci">
    <main class="login-card" role="main" aria-labelledby="titulo-login">
        <header class="login-logo">
            <div class="logo-circle" aria-hidden="true">
                <span style="font-weight:900">C</span>
            </div>
            <h1 id="titulo-login" style="margin:0;font-size:1.25rem;letter-spacing:.03em;color:var(--color-primary);font-weight:800">CONINFOMS</h1>
            <p style="margin:0;color:var(--color-text-muted);font-weight:500;font-size:.9rem">
                Sistema de Eleição &amp; Gestão de Assembleias
            </p>
        </header>

        <?php if (!empty($flash)): ?>
            <div class="alert-ci alert-ci-<?= sanitize($flash['type'] ?? 'info') ?>" role="alert">
                <?= sanitize($flash['message']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= sanitize(base_url('?route=auth/login')) ?>"
              id="form-login"
              novalidate
              aria-describedby="login-hint">
            <div class="form-group-ci">
                <label for="cpf">
                    <span aria-hidden="true">🆔</span>
                    &nbsp;Digite seu CPF
                </label>
                <input
                    type="text"
                    id="cpf"
                    name="cpf"
                    class="input-ci"
                    inputmode="numeric"
                    pattern="[0-9\.\-]{11,14}"
                    placeholder="000.000.000-00"
                    required
                    autocomplete="username"
                    maxlength="14"
                    aria-describedby="cpf-hint"
                >
                <small id="cpf-hint" class="hint">
                    Ex.: 922.633.991-00. Condôminos não precisam de senha.
                </small>
            </div>

            <div class="form-group-ci" id="senha-group" hidden>
                <label for="senha">
                    <span aria-hidden="true">🔒</span>
                    &nbsp;Senha do Administrador
                </label>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    class="input-ci"
                    placeholder="Digite sua senha administrativa"
                    autocomplete="current-password"
                    maxlength="100"
                >
                <small id="login-hint" class="hint">
                    Campo exibido automaticamente para o CPF master (Administrador).
                </small>
            </div>

            <button type="submit" id="btn-entrar"
                    class="btn-ci btn-ci-primary"
                    aria-label="Acessar o sistema">
                ▶&nbsp; Acessar Sistema
            </button>

            <div class="login-divider">Acesso Seguro · Sem senha para Condôminos</div>
        </form>

        <footer class="login-footer-ci">
            CONINFOMS Soluções em Tecnologia<br>
            Sistema projetado pelo <strong>Eng. de Software Cristiam Matos</strong><br>
            &copy; <?= (int)date('Y') ?> — Todos os direitos reservados
        </footer>
    </main>

    <script src="<?= sanitize(base_url('assets/js/app.js')) ?>"></script>
    <script>
    (function(){
        const cpf        = document.getElementById('cpf');
        const senhaGroup = document.getElementById('senha-group');
        const senha      = document.getElementById('senha');
        const form       = document.getElementById('form-login');
        const btnEntrar  = document.getElementById('btn-entrar');

        if (typeof aplicarMascaraCpf === 'function') aplicarMascaraCpf(cpf);
        // foco automatico (bom para mobile e acessibilidade)
        setTimeout(() => { try { cpf.focus({preventScroll:true}); } catch(e){} }, 150);

        function adminOn() {
            senhaGroup.hidden = false;
            senha.setAttribute('required', 'required');
            setTimeout(()=>{ try{ senha.focus(); }catch(e){} }, 150);
        }
        function adminOff() {
            senhaGroup.hidden = true;
            senha.removeAttribute('required');
            senha.value = '';
        }
        cpf.addEventListener('blur', function () {
            const raw = (this.value || '').replace(/\D/g,'');
            if (raw === '00000000000') adminOn(); else adminOff();
        });
        // Evita duplo submit e melhora UX
        form.addEventListener('submit', function () {
            btnEntrar.disabled = true;
            btnEntrar.innerHTML = '⌛ Processando...';
        });
    })();
    </script>
</body>
</html>
