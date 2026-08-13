<?php

class AuthController
{
    private $usuarioModel;
    private $unidadeModel;
    private $assembleiaModel;
    private $procuracaoModel;

    public function __construct()
    {
        $this->usuarioModel    = new UsuarioModel();
        $this->unidadeModel    = new UnidadeModel();
        $this->assembleiaModel = new AssembleiaModel();
        $this->procuracaoModel = new ProcuracaoModel();
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fallback = base_url('?route=auth/login');
            try {
                csrf_token_verify(true, $fallback);
            } catch (Throwable $e) {
                // Helper jah faz redirect. Este bloco é apenas fallback.
                security_log_exception($e, 'Auth->login(csrf)');
                redirect($fallback);
            }

            $cpf = $_POST['cpf'] ?? '';
            $cpfLimpo = preg_replace('/\D/', '', $cpf);
            $senha = $_POST['senha'] ?? '';

            if (empty($cpfLimpo)) {
                flashMessage('Por favor, informe o CPF.', 'error');
                redirect(base_url('?route=auth/login'));
            }

            try {
                $usuario = $this->usuarioModel->getByCpfRaw($cpfLimpo);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Auth->login busca usuario CPF=' . substr($cpfLimpo, 0, 3) . '***' . substr($cpfLimpo, -2));
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }

            if (!$usuario) {
                flashMessage('CPF não encontrado no sistema. Contate o síndico.', 'error');
                redirect(base_url('?route=auth/login'));
            }

            if (!$usuario['ativo']) {
                flashMessage('Usuário inativo. Contate o síndico.', 'error');
                redirect(base_url('?route=auth/login'));
            }

            $precisaSenha = false;
            $perfilRaw = (string)($usuario['perfil'] ?? $usuario['tipo']);
            if ($perfilRaw === 'admin' || $perfilRaw === 'super_admin' || $perfilRaw === 'admin_condominio') $precisaSenha = true;
            if (!$precisaSenha && !empty($usuario['senha']) && $senha !== '') $precisaSenha = true;

            if ($precisaSenha) {
                try {
                    $senhaValida = $this->usuarioModel->verificaSenha($usuario['id'], (string)$senha);
                } catch (Throwable $e) {
                    $ticket = security_log_exception($e, 'Auth->login verifica senha');
                    flashMessage(security_mensagem_amigavel($ticket), 'error');
                    redirect($fallback);
                }
                if (!$senhaValida) {
                    flashMessage('Senha incorreta.', 'error');
                    redirect(base_url('?route=auth/login'));
                }
            }

            // =============== [ RBAC - perfil normalizado + tenant id ] ===============
            // Resolve bugs de migration antiga (migration 001/003):
            //   • usuários criados ANTES da migration 003_multi_tenant_rbac tinham
            //     coluna `tipo='admin'` e `perfil=NULL` (pois perfil não existia).
            //   • No login nós normalizamos PARA SEMPRE o perfil e gravamos em
            //     AMBAS $_SESSION['usuario_perfil'] e $_SESSION['usuario_tipo'].
            //     Isso evita que helpers antigos (que só leem `tipo`) vs helpers novos
            //     (que só leem `perfil`) divergam e causem redirect para
            //     "Minha Assembleia" por falha em requireAdmin().
            $perfil  = (string)($usuario['perfil'] ?? '');
            $tipoRaw = (string)($usuario['tipo']   ?? '');
            if ($perfil === '' || $perfil === null) $perfil = $tipoRaw;   // fallback migration antiga
            if ($perfil === 'admin')                $perfil = 'super_admin';  // compat legado
            if ($tipoRaw === 'admin')               $tipoRaw = 'super_admin';

            $_SESSION['usuario_id']     = (int)$usuario['id'];
            $_SESSION['usuario_nome']   = $usuario['nome'];
            $_SESSION['usuario_cpf']    = $usuario['cpf'];
            $_SESSION['usuario_tipo']   = $perfil;           // ← GRAVA perfil normalizado AQUI também
            $_SESSION['usuario_perfil'] = $perfil;           // ← GRAVA perfil normalizado
            // Snapshot "raw" do banco, caso algum helper precise inspecionar depois (debug):
            $_SESSION['usuario_perfil_raw_db']  = (string)($usuario['perfil'] ?? '');
            $_SESSION['usuario_tipo_raw_db']    = (string)($usuario['tipo']   ?? '');
            $_SESSION['condominio_id']  = !empty($usuario['condominio_id']) ? (int)$usuario['condominio_id'] : null;

            // ======= REDIRECIONAMENTO INTELIGENTE POR PERFIL =======
            // 1) SUPER ADMIN → DIRETO para dashboard global SaaS.
            if ($perfil === 'super_admin') {
                flashMessage('Bem-vindo(a), ' . $usuario['nome'] . '! Acesso: Super Administrador (Plataforma SaaS).', 'success');
                redirect(base_url('?route=superadmin/index'));
            }
            // 2) ADMIN CONDOMÍNIO → painel do admin (visão tenant).
            if ($perfil === 'admin_condominio') {
                flashMessage('Bem-vindo(a), ' . $usuario['nome'] . '! Acesso: Gestor do Condomínio.', 'success');
                redirect(base_url('?route=admin/index'));
            }
            // 3) MORADOR → monta presença automática em assembleia aberta.
            $assembleiasAbertas = $this->assembleiaModel->getAbertaParaUsuario($usuario['id']);

            if (count($assembleiasAbertas) === 1) {
                $assembleia = $assembleiasAbertas[0];
                $this->registrarPresencaAutomatica($assembleia['id'], $usuario['id'], (int)$assembleia['condominio_id']);
                flashMessage('Presença registrada! Bem-vindo(a), ' . $usuario['nome'] . '!', 'success');
                redirect(base_url('?route=assembleia/ver/' . (int)$assembleia['id']));
            } elseif (count($assembleiasAbertas) > 1) {
                flashMessage('Bem-vindo! Selecione uma assembleia abaixo.', 'info');
                redirect(base_url('?route=assembleia/index'));
            } else {
                flashMessage('Bem-vindo! Nenhuma assembleia aberta no momento.', 'info');
                redirect(base_url('?route=assembleia/index'));
            }
        }

        $data = [
            'title' => 'Login - Sistema de Assembleias',
            'flash' => flashMessage(),
        ];

        extract($data, EXTR_SKIP);
        require __DIR__ . '/../Views/Auth/login.php';
    }

    private function registrarPresencaAutomatica($assembleiaId, $usuarioId, $condominioId)
    {
        // Usa metodo HABILITADAS (dono + procuracao + presenca manual) p/ nao perder
        // o caso onde unidade.usuario_id eh NULL mas admin jah deu presenca
        // (preferencialmente usamos a fonte A dono/procuracao aqui, mas o método
        // novo garante cobertura de qualquer camada).
        $unidades = $this->assembleiaModel->getUnidadesHabilitadasParaVoto($usuarioId, $assembleiaId, $condominioId);

        foreach ($unidades as $unidade) {
            if (!$this->assembleiaModel->hasPresenca($assembleiaId, (int)$unidade['id'])) {
                $this->assembleiaModel->registrarPresenca(
                    (int)$assembleiaId,
                    (int)$unidade['id'],
                    (int)$usuarioId,
                    (int) ($unidade['via_procuracao'] ?? 0),
                    !empty($unidade['procuracao_id']) ? (int)$unidade['procuracao_id'] : null
                );
            }
        }
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        flashMessage('Sessão encerrada com sucesso.', 'info');
        redirect(base_url('?route=auth/login'));
    }
}
