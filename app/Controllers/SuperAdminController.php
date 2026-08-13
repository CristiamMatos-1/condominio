<?php

/**
 * SuperAdminController — 🔒 ACESSO EXCLUSIVO super_admin.
 *
 * ⚠️ REQUISITO 1 (Autorização Estrita): apenas perfil `super_admin` pode
 * gerenciar cadastros de condomínios e síndicos/administradores.
 *  - __construct: trava requireSuperAdmin()
 *  - TODOS os métodos de alteração de estado (onboarding, toggle, remover,
 *    novo_condominio, editar_condominio, excluir_condominio, senha_gestor)
 *    VALIDAM CSRF token e EXIGEM method === POST.
 *
 * ⚠️ REQUISITO 3 (Segurança):
 *  - Todas exceptions capturadas → log estruturado [SEG-...] via
 *    security_log_exception() + mensagem amigável ao usuário (com ticket).
 *  - NUNCA exibimos mensagens de PDO / stack traces na UI.
 */
class SuperAdminController
{
    private $condominioModel;
    private $usuarioModel;
    private $unidadeModel;
    private $assembleiaModel;

    public function __construct()
    {
        requireSuperAdmin(); // 🔒 trava de perfil obrigatória (REQUISITO 1)
        $this->condominioModel = new CondominioModel();
        $this->usuarioModel    = new UsuarioModel();
        $this->unidadeModel    = new UnidadeModel();
        $this->assembleiaModel = new AssembleiaModel();
    }

    private function render($view, $data = [])
    {
        $data['flash'] = flashMessage();
        extract($data, EXTR_SKIP);
        require __DIR__ . '/../Views/Layouts/header_admin.php';
        require __DIR__ . '/../Views/SuperAdmin/' . $view . '.php';
        require __DIR__ . '/../Views/Layouts/footer_admin.php';
    }

    /**
     * Dashboard GLOBAL: KPIs, lista todos condomínios + onboarding admin_condominio.
     * ⚠️ GET (leitura). NÃO aplica CSRF strict — apenas para rotas de alteração.
     */
    public function index()
    {
        $condominiosAll = $this->condominioModel->getAll();
        $totalCondominiosAtivos = 0;
        $totalCondominiosInativos = 0;
        foreach ($condominiosAll as $c) {
            if ((int)($c['ativo'] ?? 1) === 1) $totalCondominiosAtivos++;
            else $totalCondominiosInativos++;
        }

        $listaUsuariosTodos = $this->usuarioModel->getAll();
        $totalUsuariosMoradores = 0;
        $totalAdminsCondominio = 0;
        foreach ($listaUsuariosTodos as $u) {
            $p = (string)($u['perfil'] ?? $u['tipo']);
            if ($p === 'admin') $p = 'super_admin';
            if ($p === 'morador' || $p === '') $totalUsuariosMoradores++;
            if ($p === 'admin_condominio') $totalAdminsCondominio++;
        }
        $totalUnidades = (int)$this->unidadeModel->count();
        $assembleiasAbertas    = count($this->assembleiaModel->getAll(null, 'Aberta'));
        $assembleiasAndamento  = count($this->assembleiaModel->getAll(null, 'Em Andamento'));
        $assembleiasEncerradas = count($this->assembleiaModel->getAll(null, 'Encerrada'));

        $stats = [
            'total_condominios_ativos'   => $totalCondominiosAtivos,
            'total_condominios_inativos' => $totalCondominiosInativos,
            'total_moradores'            => $totalUsuariosMoradores,
            'total_admin_condominio'     => $totalAdminsCondominio,
            'total_unidades'             => $totalUnidades,
            'assembleias_abertas'        => $assembleiasAbertas,
            'assembleias_andamento'      => $assembleiasAndamento,
            'assembleias_encerradas'     => $assembleiasEncerradas,
        ];

        // Lista de admin_condominio (usa perfil novo, compat com tipo legado).
        $listaUsuariosAdminsCondominio = $this->usuarioModel->getAll(null, null, 'admin_condominio');

        $this->render('dashboard', [
            'stats' => $stats,
            'condominios' => $condominiosAll,
            'listaUsuariosAdminsCondominio' => $listaUsuariosAdminsCondominio,
        ]);
    }

    public function dashboard() { $this->index(); }

    /**
     * 🔒 Onboarding: Cria conta admin_condominio (Síndico/Gestor).
     * REQUISITO 3 — EXIGE POST + CSRF token.
     */
    public function onboarding()
    {
        $fallback = base_url('?route=superadmin/index');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect($fallback);
        }
        csrf_token_verify(true, $fallback); // 🔐

        $nome            = trim((string)($_POST['nome'] ?? ''));
        $cpf             = preg_replace('/\D/', '', (string)($_POST['cpf'] ?? ''));
        $email           = trim((string)($_POST['email'] ?? ''));
        $telefone        = trim((string)($_POST['telefone'] ?? ''));
        $senhaPlana      = (string)($_POST['senha'] ?? '');
        $condominioId    = (int)($_POST['condominio_id'] ?? 0);

        if (strlen($nome) < 3 || strlen($cpf) !== 11 || $condominioId <= 0 || strlen($senhaPlana) < 6) {
            flashMessage(
                'Dados inválidos. Verifique: nome (mín. 3), CPF (11 dígitos), condomínio selecionado e senha (mín. 6).',
                'error'
            );
            redirect($fallback);
        }
        $cond = $this->condominioModel->getById($condominioId);
        if (!$cond) {
            flashMessage('Condomínio não encontrado para vincular.', 'error');
            redirect($fallback);
        }

        try {
            if ($this->usuarioModel->existsByCpf($cpf)) {
                flashMessage('CPF já cadastrado. Utilize outro CPF ou edite o gestor(a) existente.', 'error');
                redirect($fallback);
            }
            $this->usuarioModel->create([
                'nome'          => $nome,
                'cpf'           => $cpf,
                'email'         => $email,
                'telefone'      => $telefone,
                'tipo'          => 'admin_condominio',
                'perfil'        => 'admin_condominio',
                'condominio_id' => $condominioId,
                'senha'         => $senhaPlana,
            ]);
            flashMessage(
                '✅ Gestor(a) «' . sanitize($nome) . '» cadastrado(a) com sucesso. '
                . 'Perfil: Administrador do Condomínio «' . sanitize($cond['nome']) . '».',
                'success'
            );
        } catch (PDOException $e) {
            // ⚠️ Segurança: NÃO mostramos mensagem do PDO (que pode vazar
            // nomes de constraints, tabelas, colunas ou engine MySQL).
            // Logamos TUDO no error_log com ticket; mostramos só mensagem amigável.
            $ticket = security_log_exception($e, 'SuperAdmin->onboarding CPF=' . substr($cpf,0,3) . '.XXX.XXX-' . substr($cpf,-2));
            $msg = stripos($e->getMessage(), 'Duplicate') !== false
                ? 'CPF já cadastrado. Utilize outro CPF para este gestor.'
                : 'Não foi possível cadastrar. Verifique os dados e tente novamente.';
            flashMessage($msg . ' (' . sanitize($ticket) . ')', 'error');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'SuperAdmin->onboarding');
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }

        redirect($fallback);
    }

    /**
     * 🔒 Alternar ativo/suspenso de um condomínio (toggle).
     * MIGRADO DE GET → POST (ataques CSRF/preload/iframe).
     */
    public function condominio_toggle_post()
    {
        $fallback = base_url('?route=superadmin/index');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect($fallback);
        csrf_token_verify(true, $fallback);

        $id = (int)($_POST['condominio_id'] ?? 0);
        $c = $this->condominioModel->getById($id);
        if (!$c) {
            flashMessage('Condomínio não encontrado.', 'error');
            redirect($fallback);
        }
        try {
            $novoAtivo = ((int)($c['ativo'] ?? 1) === 1) ? 0 : 1;
            // Update via payload validado (não usa $_POST bruto).
            $this->condominioModel->update($c['id'], [
                'nome'     => $c['nome'],
                'cnpj'     => $c['cnpj'] ?? null,
                'endereco' => $c['endereco'] ?? null,
                'cidade'   => $c['cidade'] ?? null,
                'estado'   => $c['estado'] ?? null,
                'cep'      => $c['cep'] ?? null,
                'email'    => $c['email'] ?? null,
                'telefone' => $c['telefone'] ?? null,
                'ativo'    => $novoAtivo,
            ]);
            flashMessage(
                'Condomínio «' . sanitize($c['nome']) . '» '
                . ($novoAtivo ? 'reativado ✅' : 'suspenso 🚫') . '.',
                'success'
            );
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'SuperAdmin->condominio_toggle id=' . $id);
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }
        redirect($fallback);
    }

    /**
     * 🔒 Excluir conta de admin_condominio (NÃO remove contas super_admin).
     * MIGRADO DE GET → POST.
     */
    public function admin_condominio_remover_post()
    {
        $fallback = base_url('?route=superadmin/index');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect($fallback);
        csrf_token_verify(true, $fallback);

        $userId = (int)($_POST['usuario_id'] ?? 0);
        try {
            $u = $this->usuarioModel->getById($userId);
            if (!$u) {
                flashMessage('Usuário não encontrado.', 'error');
                redirect($fallback);
            }
            $perfil = (string)($u['perfil'] ?? $u['tipo']);
            if ($perfil === 'admin') $perfil = 'super_admin';
            if ($perfil === 'super_admin') {
                flashMessage('Não é permitido remover contas de Super Administrador.', 'error');
                redirect($fallback);
            }
            if ($perfil !== 'admin_condominio') {
                flashMessage('Este usuário não é um Administrador de Condomínio.', 'error');
                redirect($fallback);
            }
            $this->usuarioModel->delete($userId);
            flashMessage('Conta do(a) gestor(a) «' . sanitize($u['nome']) . '» foi removida.', 'success');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'SuperAdmin->admin_condominio_remover id=' . $userId);
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }
        redirect($fallback);
    }

    // ========== ALIASES LEGADOS — mantidos p/ não quebrar links antigos. ==========
    // Redirecionam para as rotas POST (no formato novo).
    public function condominio_toggle($id)
    {
        flashMessage('Esta ação deve ser feita pelo botão na tabela (método seguro).', 'warning');
        redirect(base_url('?route=superadmin/index'));
    }
    public function admin_condominio_remover($userId)
    {
        flashMessage('Esta ação deve ser feita pelo botão na tabela (método seguro).', 'warning');
        redirect(base_url('?route=superadmin/index'));
    }

    // =========================================================================
    // 🏢 CRUD NATIVO DE CONDOMÍNIOS DENTRO DO SUPER ADMIN (SaaS)
    // ⚠️ Não depende mais do AdminController para o fluxo de gerenciamento
    // de condomínios. SuperAdmin gerencia tudo dentro de rotas superadmin/*
    // =========================================================================

    /**
     * Reutiliza a view de formulário de condomínio (Admin/condominio_form)
     * mas aponta a action para as rotas nativas do Super Admin.
     */
    private function renderCondominioForm(?array $condominio, string $actionUrl, string $voltarUrl)
    {
        $flash = flashMessage();
        extract([
            'condominio' => $condominio,
            'form_action' => $actionUrl,
            'voltar_url' => $voltarUrl,
            'flash' => $flash,
        ], EXTR_SKIP);
        require __DIR__ . '/../Views/Layouts/header_admin.php';
        require __DIR__ . '/../Views/Admin/condominio_form.php';
        require __DIR__ . '/../Views/Layouts/footer_admin.php';
    }

    private function payloadCondominioSeguro(array $src): array
    {
        // Mass assignment bloqueado: só colunas permitidas.
        // O Model CondominioModel já faz a limpeza/validação final (CNPJ 14 dígitos, CEP 8).
        return [
            'nome'     => trim((string)($src['nome'] ?? '')),
            'cnpj'     => $src['cnpj']     ?? null,
            'endereco' => $src['endereco'] ?? null,
            'cidade'   => $src['cidade']   ?? null,
            'estado'   => $src['estado']   ?? null,
            'cep'      => $src['cep']      ?? null,
            'email'    => $src['email']    ?? null,
            'telefone' => $src['telefone'] ?? null,
            'ativo'    => isset($src['ativo']) ? (int)$src['ativo'] : 1,
        ];
    }

    public function condominio_novo()
    {
        $fallback = base_url('?route=superadmin/index');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = $this->payloadCondominioSeguro($_POST);
                if (strlen($payload['nome']) < 3) {
                    flashMessage('Nome do condomínio inválido (mínimo 3 letras).', 'error');
                    redirect(base_url('?route=superadmin/condominio_novo'));
                }
                $this->condominioModel->create($payload);
                flashMessage('✅ Condomínio cadastrado com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'SuperAdmin->condominio_novo');
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect(base_url('?route=superadmin/condominio_novo'));
            }
        }
        $this->renderCondominioForm(null, base_url('?route=superadmin/condominio_novo'), $fallback);
    }

    public function condominio_editar($id)
    {
        $fallback = base_url('?route=superadmin/index');
        $condominio = $this->condominioModel->getById((int)$id);
        if (!$condominio) {
            flashMessage('Condomínio não encontrado.', 'error');
            redirect($fallback);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = $this->payloadCondominioSeguro($_POST);
                if (strlen($payload['nome']) < 3) {
                    flashMessage('Nome do condomínio inválido (mínimo 3 letras).', 'error');
                    redirect(base_url('?route=superadmin/condominio_editar/' . (int)$id));
                }
                $this->condominioModel->update($condominio['id'], $payload);
                flashMessage('✅ Condomínio atualizado com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'SuperAdmin->condominio_editar id=' . (int)$id);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect(base_url('?route=superadmin/condominio_editar/' . (int)$id));
            }
        }
        $this->renderCondominioForm(
            $condominio,
            base_url('?route=superadmin/condominio_editar/' . (int)$id),
            $fallback
        );
    }

    public function condominio_excluir_post()
    {
        $fallback = base_url('?route=superadmin/index');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect($fallback);
        csrf_token_verify(true, $fallback);
        $id = (int)($_POST['condominio_id'] ?? 0);
        $c = $this->condominioModel->getById($id);
        if (!$c) {
            flashMessage('Condomínio não encontrado.', 'error');
            redirect($fallback);
        }
        try {
            $this->condominioModel->delete($id);
            flashMessage('Condomínio «' . sanitize($c['nome']) . '» foi removido.', 'success');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'SuperAdmin->condominio_excluir id=' . $id);
            $msg = stripos($e->getMessage(), 'Integrity') !== false || stripos($e->getMessage(), 'foreign') !== false
                ? 'Não foi possível remover: este condomínio tem unidades, usuários ou assembleias vinculadas (remova estes primeiro).'
                : security_mensagem_amigavel($ticket);
            flashMessage($msg, 'error');
        }
        redirect($fallback);
    }
}
