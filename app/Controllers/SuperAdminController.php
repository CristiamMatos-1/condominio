<?php

/**
 * SuperAdminController — Acesso APENAS super_admin (perfil 'super_admin').
 * Dashboard GLOBAL da Plataforma SaaS: todos os condomínios cadastrados,
 * KPIs globais (ativos/inativos, moradores, etc) e onboarding (criar conta
 * de Admin de Condomínio para um condomínio cadastrado).
 */
class SuperAdminController
{
    private $condominioModel;
    private $usuarioModel;
    private $unidadeModel;
    private $assembleiaModel;

    public function __construct()
    {
        requireSuperAdmin(); // 🔒 trava de perfil
        $this->condominioModel = new CondominioModel();
        $this->usuarioModel    = new UsuarioModel();
        $this->unidadeModel    = new UnidadeModel();
        $this->assembleiaModel = new AssembleiaModel();
    }

    private function render($view, $data = [])
    {
        $data['flash'] = flashMessage();
        extract($data);
        require __DIR__ . '/../Views/Layouts/header_admin.php';
        require __DIR__ . '/../Views/SuperAdmin/' . $view . '.php';
        require __DIR__ . '/../Views/Layouts/footer_admin.php';
    }

    /**
     * Dashboard GLOBAL: KPIs gerais + lista de todos condominios + form onboarding (criar admin_condominio).
     */
    public function index()
    {
        $condominiosAll = $this->condominioModel->getAll(); // Todos, inclusive inativos (super admin ve tudo)
        $totalCondominiosAtivos  = 0;
        $totalCondominiosInativos = 0;
        foreach ($condominiosAll as $c) {
            if (in_array((int)($c['ativo'] ?? 1), [1], true)) $totalCondominiosAtivos++;
            else $totalCondominiosInativos++;
        }
        $totalUsuariosMoradores   = count(array_filter(
            $this->usuarioModel->getAll(),
            static fn($u) => in_array(($u['perfil'] ?? $u['tipo']), ['morador', null], true)
        ));
        $totalAdminsCondominio    = count(array_filter(
            $this->usuarioModel->getAll(),
            static fn($u) => ($u['perfil'] ?? '') === 'admin_condominio'
        ));
        $totalUnidades            = (int)$this->unidadeModel->count();
        $assembleiasAbertas       = count($this->assembleiaModel->getAll(null, 'Aberta'));
        $assembleiasAndamento     = count($this->assembleiaModel->getAll(null, 'Em Andamento'));
        $assembleiasEncerradas    = count($this->assembleiaModel->getAll(null, 'Encerrada'));
        $stats = [
            'total_condominios_ativos'     => $totalCondominiosAtivos,
            'total_condominios_inativos'   => $totalCondominiosInativos,
            'total_moradores'              => $totalUsuariosMoradores,
            'total_admin_condominio'       => $totalAdminsCondominio,
            'total_unidades'               => $totalUnidades,
            'assembleias_abertas'          => $assembleiasAbertas,
            'assembleias_andamento'        => $assembleiasAndamento,
            'assembleias_encerradas'       => $assembleiasEncerradas,
        ];
        $listaUsuariosAdminsCondominio = $this->usuarioModel->getAll('admin_condominio'); // compat getall(tipo)
        // View espera: $stats, $condominios, $listaUsuariosAdminsCondominio
        $this->render('dashboard', [
            'stats' => $stats,
            'condominios' => $condominiosAll,
            'listaUsuariosAdminsCondominio' => $listaUsuariosAdminsCondominio,
        ]);
    }

    /**
     * Alias de conveniência (admin chama ?route=superadmin/dashboard).
     */
    public function dashboard() { $this->index(); }

    /**
     * Onboarding: cria uma conta "admin_condominio" (Síndico/Gestor) com
     * condominio_id fixado. Usada quando o super_admin cadastra um cliente.
     * POST → redirect de volta p/ dashboard.
     */
    public function onboarding()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('?route=superadmin/index'));
        }
        $nome            = trim((string)($_POST['nome'] ?? ''));
        $cpf             = preg_replace('/[^0-9]/', '', (string)($_POST['cpf'] ?? ''));
        $email           = trim((string)($_POST['email'] ?? ''));
        $telefone        = trim((string)($_POST['telefone'] ?? ''));
        $senhaPlana      = (string)($_POST['senha'] ?? '');
        $condominioId    = (int)($_POST['condominio_id'] ?? 0);

        if (strlen($nome) < 3 || strlen($cpf) !== 11 || $condominioId <= 0 || strlen($senhaPlana) < 6) {
            flashMessage(
                'Dados inválidos. Verifique: nome (mín 3), CPF (11 dígitos), condomínio selecionado, senha (mín 6).',
                'error'
            );
            redirect(base_url('?route=superadmin/index'));
        }
        $cond = $this->condominioModel->getById($condominioId);
        if (!$cond) {
            flashMessage('Condomínio não encontrado para vincular.', 'error');
            redirect(base_url('?route=superadmin/index'));
        }
        try {
            $this->usuarioModel->create([
                'nome'          => $nome,
                'cpf'           => $cpf,
                'email'         => $email,
                'telefone'      => $telefone,
                // tipo legado + perfil novo: ambos enviamos p/ UsuarioModel.create()
                // (o create aceita ambos; se coluna perfil existe usa, senão tipo).
                'tipo'          => 'admin_condominio',
                'perfil'        => 'admin_condominio',
                'condominio_id' => $condominioId,
                'senha'         => $senhaPlana,
            ]);
            flashMessage(
                'Gestor(a) ' . htmlspecialchars($nome) . ' cadastrado(a) com sucesso. '
                .'Perfil: Administrador do Condomínio «' . htmlspecialchars($cond['nome']) . '».',
                'success'
            );
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false) {
                flashMessage('CPF já cadastrado. Utilize outro CPF para este gestor.', 'error');
            } else {
                flashMessage('Erro ao cadastrar gestor: ' . $msg, 'error');
            }
        }
        redirect(base_url('?route=superadmin/index'));
    }

    /**
     * Aprovar / Suspender / Reativar condomínio (toggle ativo).
     * Route: ?route=superadmin/condominio_toggle/{id}
     */
    public function condominio_toggle($id)
    {
        $c = $this->condominioModel->getById((int)$id);
        if (!$c) {
            flashMessage('Condomínio não encontrado.', 'error');
            redirect(base_url('?route=superadmin/index'));
        }
        $novoAtivo = ((int)($c['ativo'] ?? 1) === 1) ? 0 : 1;
        $this->condominioModel->update($c['id'], [
            'nome'      => $c['nome'],
            'cnpj'      => $c['cnpj'] ?? null,
            'endereco'  => $c['endereco'] ?? null,
            'email'     => $c['email'] ?? null,
            'telefone'  => $c['telefone'] ?? null,
            'ativo'     => $novoAtivo,
        ]);
        flashMessage(
            'Condomínio «' . htmlspecialchars($c['nome']) . '» '
            . ($novoAtivo ? 'reativado ✅' : 'suspenso 🚫') . '.',
            'success'
        );
        redirect(base_url('?route=superadmin/index'));
    }

    /**
     * Excluir conta de admin_condominio (não apaga super admin, protege).
     * Route: ?route=superadmin/admin_condominio_remover/{userId}
     */
    public function admin_condominio_remover($userId)
    {
        $userId = (int)$userId;
        $u = $this->usuarioModel->getById($userId);
        if (!$u) {
            flashMessage('Usuário não encontrado.', 'error');
            redirect(base_url('?route=superadmin/index'));
        }
        $perfil = $u['perfil'] ?? $u['tipo'];
        if ($perfil === 'super_admin' || $perfil === 'admin') {
            flashMessage('Não é permitido remover contas de Super Administrador.', 'error');
            redirect(base_url('?route=superadmin/index'));
        }
        if ($perfil !== 'admin_condominio') {
            flashMessage('Este usuário não é um Administrador de Condomínio.', 'error');
            redirect(base_url('?route=superadmin/index'));
        }
        $this->usuarioModel->delete($userId);
        flashMessage(
            'Conta do(a) gestor(a) «' . htmlspecialchars($u['nome']) . '» foi removida.',
            'success'
        );
        redirect(base_url('?route=superadmin/index'));
    }
}
