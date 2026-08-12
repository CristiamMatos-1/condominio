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
            } elseif ($usuario['tipo'] === 'morador' && !empty($usuario['senha']) && !empty($senha)) {
                if (!$this->usuarioModel->verificaSenha($usuario['id'], $senha)) {
                    flashMessage('Senha incorreta.', 'error');
                    redirect(base_url('?route=auth/login'));
                }
            }

            $_SESSION['usuario_id']   = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_cpf']  = $usuario['cpf'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            if ($usuario['tipo'] === 'admin') {
                flashMessage('Bem-vindo, ' . $usuario['nome'] . '!', 'success');
                redirect(base_url('?route=admin/index'));
            }

            $assembleiasAbertas = $this->assembleiaModel->getAbertaParaUsuario($usuario['id']);

            if (count($assembleiasAbertas) === 1) {
                $assembleia = $assembleiasAbertas[0];
                $this->registrarPresencaAutomatica($assembleia['id'], $usuario['id'], $assembleia['condominio_id']);
                flashMessage('Presença registrada! Bem-vindo, ' . $usuario['nome'] . '!', 'success');
                redirect(base_url('?route=assembleia/ver/' . $assembleia['id']));
            } elseif (count($assembleiasAbertas) > 1) {
                flashMessage('Bem-vindo! Selecione uma assembleia.', 'info');
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
        $unidades = $this->procuracaoModel->getTodasUnidadesUsuario($usuarioId, $condominioId);

        foreach ($unidades as $unidade) {
            if (!$this->assembleiaModel->hasPresenca($assembleiaId, $unidade['id'])) {
                $this->assembleiaModel->registrarPresenca(
                    $assembleiaId,
                    $unidade['id'],
                    $usuarioId,
                    (int) $unidade['via_procuracao'],
                    !empty($unidade['procuracao_id']) ? $unidade['procuracao_id'] : null
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
