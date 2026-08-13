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
    <link rel="stylesheet" href="<?= sanitize(asset_url('css/style.css')) ?>">
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
                    Ex.: 000.000.000-00. Condôminos não precisam de senha.
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

            <!-- ===== Cards de Acesso Rápido (Síndico/Gestor + Super Admin) ===== -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:8px;margin-bottom:8px;">
                <!-- Link 1: SÍNDICO / GESTOR do Condomínio -->
                <button type="button"
                        class="btn-ci btn-ci-outline"
                        style="
                            text-align:left;
                            padding:14px 16px;
                            border:1.5px solid var(--color-secondary,#D97706);
                            background:linear-gradient(90deg,#FFFBEB 0%,#FEF3C7 100%);
                            color:#92400E;
                            box-shadow:var(--shadow-sm, 0 1px 2px 0 rgb(0 0 0 / 0.05));
                        "
                        data-papel="admin_condominio"
                        aria-label="Acesso rápido do síndico ou gestor do condomínio">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="
                            width:40px;height:40px;border-radius:10px;
                            background:var(--color-secondary,#D97706);
                            color:white;display:grid;place-items:center;font-size:1.1rem;flex-shrink:0;
                        ">🧑‍💼</span>
                        <div style="line-height:1.2;">
                            <div style="font-weight:700;font-size:1rem;color:#92400E;">Acesso Síndico / Gestor</div>
                            <small style="color:#78350F;">Administrador do seu condomínio</small>
                        </div>
                    </div>
                </button>
                <!-- Link 2: SUPER ADMIN (Administrador da Plataforma / Suporte) -->
                <button type="button"
                        class="btn-ci btn-ci-outline"
                        style="
                            text-align:left;
                            padding:14px 16px;
                            border:1.5px solid var(--color-primary,#1E40AF);
                            background:linear-gradient(90deg,#EFF6FF 0%,#DBEAFE 100%);
                            color:#1E3A8A;
                            box-shadow:var(--shadow-sm, 0 1px 2px 0 rgb(0 0 0 / 0.05));
                        "
                        data-papel="super_admin"
                        aria-label="Acesso rápido do super administrador da plataforma">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="
                            width:40px;height:40px;border-radius:10px;
                            background:var(--color-primary,#1E40AF);
                            color:white;display:grid;place-items:center;font-size:1.1rem;flex-shrink:0;
                        ">🛡️</span>
                        <div style="line-height:1.2;">
                            <div style="font-weight:700;font-size:1rem;color:#1E3A8A;">Acesso Super Admin</div>
                            <small style="color:#1E40AF;">Administrador Geral da Plataforma</small>
                        </div>
                    </div>
                </button>
            </div>
            <p class="hint" style="margin:8px 0 0 0;font-size:.78rem;text-align:center;color:var(--color-text-muted,#6B7280);">
                💡 Clique em um dos cards acima para ativar o campo de senha e digitar o seu CPF.
            </p>
            <?= csrf_field() ?>
        </form>

        <footer class="login-footer-ci">
            CONINFOMS Soluções em Tecnologia<br>
            Sistema projetado pelo <strong>Eng. de Software Cristiam Matos</strong><br>
            &copy; <?= (int)date('Y') ?> — Todos os direitos reservados
        </footer>
    </main>

    <script src="<?= sanitize(asset_url('js/app.js')) ?>"></script>
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

        function adminOn(opcoes) {
            opcoes = opcoes || {};
            // Modo administrativo LIGADO: deixa o campo senha VISIVEL ate que o usuario
            // resolva desligar manualmente (listener blur NAO desativa mais qdo modoAdmin=1).
            if (opcoes.marcarModoAdmin !== false) cpf.dataset.modoAdmin = '1';
            senhaGroup.hidden = false;
            senha.setAttribute('required', 'required');
            // Nao forca foco automatico na senha se opcoes.focarSenha for false.
            if (opcoes.focarSenha !== false) {
                setTimeout(function(){ try{ senha.focus(); }catch(e){} }, 150);
            }
        }
        function adminOff(opcoes) {
            opcoes = opcoes || {};
            // Apenas apaga a flag de modo administrativo se qdo chamado for explicitamente
            // para reset total (ex.: CPF totalmente vazio).
            if (opcoes.resetModoAdmin === true) cpf.dataset.modoAdmin = '';
            senhaGroup.hidden = true;
            senha.removeAttribute('required');
            senha.value = '';
        }
        function modoAdminAtivo() {
            return cpf.dataset.modoAdmin === '1';
        }
        function mascararCpf(valorRaw) {
            const v = (valorRaw || '').replace(/\D/g,'').slice(0,11);
            if (v.length > 9)  return v.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2})/,  '$1.$2.$3-$4');
            if (v.length > 6)  return v.replace(/^(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
            if (v.length > 3)  return v.replace(/^(\d{3})(\d{0,3})/,       '$1.$2');
            return v;
        }
        cpf.addEventListener('input', function(){
            this.value = mascararCpf(this.value);
            // Se usuario esta digitando um CPF novo (diferente do setado via card),
            // NÃO desliga o modo admin. Só desliga modo admin se apagar TUDO.
            if (this.value === '') adminOff({resetModoAdmin:true});
        });

        // ========== REGRA CRÍTICA CORRIGIDA ==========
        // O blur NÃO fecha mais o campo de senha se:
        //   A) Modo administrativo ESTA LIGADO (usuario clicou em um card Super Admin/Síndico)
        //   B) CPF tiver 11 dígitos (CPF completo = provavel administrador/gestor)
        // Somente FECHA o campo senha se cpf FOR VAZIO e sem modo admin.
        cpf.addEventListener('blur', function () {
            const raw = (this.value || '').replace(/\D/g,'');

            if (modoAdminAtivo()) {
                // 👉 Correção BUG 1 e BUG 2: qdo clicar em um dos cards, o modoAdmin
                // esta '1' — entao MANTEM o campo senha ABERTO, nao fecha mais.
                adminOn({marcarModoAdmin:false, focarSenha:false});
                return;
            }

            // Usuario nao clicou em nenhum card. Se CPF for um CPF COMPLETO (11 digitos),
            // abre o campo senha tambem — flexivel para admins/gestores que nao usam os cards.
            if (raw.length === 11) {
                adminOn({marcarModoAdmin:false, focarSenha:false});
                return;
            }

            // CPF vazio ou incompleto e sem modo admin → fecha campo senha (limpa).
            if (raw === '') {
                adminOff({resetModoAdmin:true});
                return;
            }

            // CPF digitado porem incompleto → mantem o estado anterior, nao muda nada.
        });

        // Evita duplo submit e melhora UX
        form.addEventListener('submit', function () {
            btnEntrar.disabled = true;
            btnEntrar.innerHTML = '⌛ Processando...';
        });

        // ========= CARDS DE ACESSO RAPIDO (botões) =========
        //  - Card SÍNDICO/GESTOR: limpa CPF e liga modo admin (usuário digita manualmente).
        //  - Card SUPER ADMIN: NÃO preenche CPF automaticamente (LGPD/privacidade).
        //    Liga modo admin, mostra placeholder específico e foca no input para
        //    o usuário digitar o seu CPF manualmente.
        document.querySelectorAll('[data-papel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const papel = this.getAttribute('data-papel');
                if (papel === 'super_admin') {
                    // ⚠️ LGPD: NÃO preencher CPF do Super Admin automaticamente.
                    // O usuário deve digitar manualmente.
                    cpf.value = '';
                    cpf.placeholder = 'Digite o CPF do Super Administrador';
                    // Liga modo admin (campo senha aberto) e foca no input CPF.
                    adminOn({marcarModoAdmin:true, focarSenha:false});
                    // Feedback visual: borda azul animada por 3 segundos.
                    cpf.style.outline = '2px solid #1E40AF';
                    cpf.style.outlineOffset = '2px';
                    setTimeout(function(){
                        cpf.style.outline = '';
                        cpf.style.outlineOffset = '';
                        cpf.placeholder = '000.000.000-00';
                    }, 3000);
                    setTimeout(function(){
                        try { cpf.focus({preventScroll:true}); } catch (e){}
                    }, 60);
                } else if (papel === 'admin_condominio') {
                    // Síndico / Gestor — liga modo admin, limpa CPF, foca input CPF,
                    // marca input com borda dourada animada, placeholder personalizado.
                    cpf.value = '';
                    cpf.placeholder = 'Digite o CPF do(a) Síndico(a)/Gestor(a)';
                    // Marca modo admin para o NAO fechar campo senha no blur depois
                    // que usuario digitar o CPF do sindico (agora BUG 2 resolvido).
                    adminOn({marcarModoAdmin:true, focarSenha:false});
                    // Marca input com borda dourada (feedback visual)
                    cpf.style.outline = '2px solid #D97706';
                    cpf.style.outlineOffset = '2px';
                    setTimeout(function(){
                        cpf.style.outline = '';
                        cpf.style.outlineOffset = '';
                        cpf.placeholder = '000.000.000-00';
                    }, 3000);
                    setTimeout(function(){
                        try { cpf.focus({preventScroll:true}); } catch (e){}
                    }, 60);
                }
            });
        });
    })();
    </script>
</body>
</html>
