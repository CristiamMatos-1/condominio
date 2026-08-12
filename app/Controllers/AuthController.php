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
            $cpf = $_POST['cpf'] ?? '';
            $cpfLimpo = preg_replace('/\D/', '', $cpf);
            $senha = $_POST['senha'] ?? '';

            if (empty($cpfLimpo)) {
                flashMessage('Por favor, informe o CPF.', 'error');
                redirect(base_url('?route=auth/login'));
            }

            $usuario = $this->usuarioModel->getByCpfRaw($cpfLimpo);

            if (!$usuario) {
                flashMessage('CPF não encontrado no sistema. Contate o síndico.', 'error');
                redirect(base_url('?route=auth/login'));
            }

            if (!$usuario['ativo']) {
                flashMessage('Usuário inativo. Contate o síndico.', 'error');
                redirect(base_url('?route=auth/login'));
            }

            if ($usuario['tipo'] === 'admin' && !empty($senha)) {
                if (!$this->usuarioModel->verificaSenha($usuario['id'], $senha)) {
                    flashMessage('Senha incorreta.', 'error');
                    redirect(base_url('?route=auth/login'));
                }
            } elseif ($usuario['tipo'] !== 'admin' && !empty($usuario['senha']) && !empty($senha)) {
                // Qualquer perfil NAO-admin (admin_condominio ou morador) com senha cadastrada
                // pode validar via senha tambem (flexibilidade).
                if (!$this->usuarioModel->verificaSenha($usuario['id'], $senha)) {
                    flashMessage('Senha incorreta.', 'error');
                    redirect(base_url('?route=auth/login'));
                }
            }

            // =============== [ RBAC - perfil normalizado + tenant id ] ===============
            $perfil = (string)($usuario['perfil'] ?? $usuario['tipo']);
            if ($perfil === 'admin') $perfil = 'super_admin'; // compat legado migration 003

            $_SESSION['usuario_id']   = (int)$usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_cpf']  = $usuario['cpf'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];   // legado
            $_SESSION['usuario_perfil'] = $perfil;          // principal
            $_SESSION['condominio_id']  = !empty($usuario['condominio_id']) ? (int)$usuario['condominio_id'] : null;

            // ======= REDIRECIONAMENTO INTELIGENTE POR PERFIL =======
            // 1) SUPER ADMIN e ADMIN CONDOMINIO → vão para painel administrativo
            if ($perfil === 'super_admin' || $perfil === 'admin_condominio') {
                flashMessage('Bem-vindo(a), ' . $usuario['nome'] . '!', 'success');
                redirect(base_url('?route=admin/index'));
            }

            // 2) MORADOR → procura assembleia ABERTA automatica do seu condominio
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

        extract($data);
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
