<?php

/**
 * AdminController — Dupla responsabilidade com separação estrita de perfil:
 *
 *  • SUPER ADMIN: acesso a TODAS as rotas (incluindo CRUD de condomínios e
 *    CRUD global de usuários/gestores).
 *  • ADMIN CONDOMÍNIO (Síndico/Gestor): acesso APENAS às rotas do seu
 *    TENANT (unidades, procurações, assembleias, pautas, chapas, presenças).
 *    NÃO pode criar, editar ou remover outro condomínio ou outro gestor.
 *
 * Proteções aplicadas:
 *  - Todos os métodos que alteram estado (POST) validam CSRF.
 *  - Payloads de create/update validados CAMPO-A-CAMPO (nunca $_POST bruto).
 *  - Exceções capturadas: log estruturado [SEG-...] + mensagem amigável c/ ticket.
 */
class AdminController
{
    private $condominioModel;
    private $usuarioModel;
    private $unidadeModel;
    private $procuracaoModel;
    private $assembleiaModel;
    private $pautaModel;
    private $chapaModel;

    public function __construct()
    {
        requireAdmin();
        $this->condominioModel  = new CondominioModel();
        $this->usuarioModel     = new UsuarioModel();
        $this->unidadeModel     = new UnidadeModel();
        $this->procuracaoModel  = new ProcuracaoModel();
        $this->assembleiaModel  = new AssembleiaModel();
        $this->pautaModel       = new PautaModel();
        $this->chapaModel       = new ChapaModel();
    }

    private function render($view, $data = [])
    {
        $data['flash'] = flashMessage();
        extract($data, EXTR_SKIP);
        require __DIR__ . '/../Views/Layouts/header_admin.php';
        require __DIR__ . '/../Views/Admin/' . $view . '.php';
        require __DIR__ . '/../Views/Layouts/footer_admin.php';
    }

    // ==================== REDIRECIONAMENTO POR PERFIL ======================
    // Admin_condominio cai no dashboard do tenant.
    // Super_admin: ANTES redirecionava para superadmin/index, agora DEIXA o
    // Super Admin usar o Painel Admin Geral (listagem geral de modulos) se
    // ele quiser. Apenas se ele veio de uma rota default vazia sem contexto.
    // =======================================================================
    public function index()
    {
        // Super admin pode usar este dashboard também — só redirect se ele
        // veio por engano e queremos manter o fluxo inicial. Mas aqui nós
        // deixamos ele ficar (menu do Painel Admin Geral é útil para gestão
        // global de usuários, unidades, procurações, assembleias).
        $condominioId = condominioIdSessao();
        if (!isSuperAdmin() && $condominioId === null) {
            // admin_condominio sem condominio_id vinculado — tenta log out.
            flashMessage('Conta de gestor(a) não está vinculada a um condomínio. Contate o suporte.', 'error');
            redirect(base_url('?route=auth/logout'));
        }
        $stats = [
            'total_condominios'  => $this->condominioModel->count(1),
            'total_usuarios'     => count($this->usuarioModel->getAll(null, 1, null, $condominioId)),
            'total_unidades'     => $this->unidadeModel->count($condominioId),
            'total_assembleias'  => count($this->assembleiaModel->getAll($condominioId)),
            'assembleias_abertas'=> count($this->assembleiaModel->getAll($condominioId, 'Aberta')),
        ];
        $ultimasAssembleias = $this->assembleiaModel->getAll($condominioId);
        $this->render('dashboard', compact('stats', 'ultimasAssembleias'));
    }
    public function dashboard() { $this->index(); }

    // =======================================================================
    // 🔒 CRUD DE CONDOMÍNIOS — REQUISITO 1: SOMENTE SUPER_ADMIN.
    // Qualquer acesso por admin_condominio: negado com flash.
    // =======================================================================
    public function condominios()
    {
        if (!isSuperAdmin()) {
            flashMessage('Acesso restrito ao Super Administrador.', 'error');
            redirect(base_url('?route=admin/index'));
        }
        $condominios = $this->condominioModel->getAll();
        $this->render('condominios', compact('condominios'));
    }

    public function condominio_novo()
    {
        if (!isSuperAdmin()) {
            flashMessage('Acesso restrito ao Super Administrador.', 'error');
            redirect(base_url('?route=admin/index'));
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fallback = base_url('?route=admin/condominios');
            csrf_token_verify(true, $fallback);
            try {
                $this->condominioModel->create($this->payloadCondominio($_POST));
                flashMessage('Condomínio cadastrado com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->condominio_novo');
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $this->render('condominio_form', ['condominio' => null]);
    }

    public function condominio_editar($id)
    {
        if (!isSuperAdmin()) {
            flashMessage('Acesso restrito ao Super Administrador.', 'error');
            redirect(base_url('?route=admin/index'));
        }
        $fallback = base_url('?route=admin/condominios');
        $condominio = $this->condominioModel->getById((int)$id);
        if (!$condominio) {
            flashMessage('Condomínio não encontrado.', 'error');
            redirect($fallback);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $this->condominioModel->update($condominio['id'], $this->payloadCondominio($_POST));
                flashMessage('Condomínio atualizado com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->condominio_editar id=' . $id);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $this->render('condominio_form', compact('condominio'));
    }

    public function condominio_excluir($id)
    {
        if (!isSuperAdmin()) {
            flashMessage('Acesso restrito ao Super Administrador.', 'error');
            redirect(base_url('?route=admin/index'));
        }
        $fallback = base_url('?route=admin/condominios');
        // Exclusão preferencialmente via POST (proteção CSRF).
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
        }
        try {
            $this->condominioModel->delete((int)$id);
            flashMessage('Condomínio removido com sucesso!', 'success');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'Admin->condominio_excluir id=' . $id);
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }
        redirect($fallback);
    }

    // =======================================================================
    // 🔒 CRUD DE USUÁRIOS — REQUISITO 1: SOMENTE SUPER_ADMIN pode ver todos.
    // Admin_condominio pode GERENCIAR moradores do SEU condomínio (listar
    // apenas os usuários vinculados).
    // =======================================================================
    public function usuarios()
    {
        if (isSuperAdmin()) {
            $usuarios = $this->usuarioModel->getAll();
        } else {
            $cid = condominioIdSessao();
            $usuarios = $this->usuarioModel->getAll(null, 1, null, $cid);
        }
        $this->render('usuarios', compact('usuarios'));
    }

    public function usuario_novo()
    {
        $fallback = base_url('?route=admin/usuarios');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = $this->payloadUsuarioCriar($_POST);
                // Segurança: se é admin_condominio logado, FORÇA o condomínio_id
                // da sessão — evita criar usuário em outro tenant via manipulacao de HTML.
                if (!isSuperAdmin()) {
                    $payload['condominio_id'] = condominioIdSessao();
                    $payload['perfil']        = 'morador';
                    $payload['tipo']          = 'morador';
                }
                $this->usuarioModel->create($payload);
                flashMessage('Usuário cadastrado com sucesso!', 'success');
                redirect($fallback);
            } catch (PDOException $e) {
                $ticket = security_log_exception($e, 'Admin->usuario_novo');
                $msg = stripos($e->getMessage(), 'Duplicate') !== false
                    ? 'CPF já cadastrado. Use outro CPF.'
                    : 'Não foi possível cadastrar o usuário.';
                flashMessage($msg . ' (' . sanitize($ticket) . ')', 'error');
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->usuario_novo');
                flashMessage(security_mensagem_amigavel($ticket), 'error');
            }
            redirect($fallback);
        }
        $condominios = isSuperAdmin() ? $this->condominioModel->getAll(1) : [];
        $this->render('usuario_form', ['usuario' => null, 'condominios' => $condominios]);
    }

    public function usuario_editar($id)
    {
        $fallback = base_url('?route=admin/usuarios');
        $usuario = $this->usuarioModel->getById((int)$id);
        if (!$usuario) {
            flashMessage('Usuário não encontrado.', 'error');
            redirect($fallback);
        }
        // Segurança: admin_condominio só pode editar usuários do SEU tenant.
        if (!isSuperAdmin()) {
            $cidSessao = (int)condominioIdSessao();
            if ((int)($usuario['condominio_id'] ?? 0) !== $cidSessao || (int)$cidSessao <= 0) {
                flashMessage('Você não tem permissão para editar este usuário.', 'error');
                redirect($fallback);
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = $this->payloadUsuarioAtualizar($_POST);
                // Super admin pode trocar perfil e condomínio_id.
                // Admin_condominio NÃO pode: trava no seu tenant e perfíl = morador.
                if (!isSuperAdmin()) {
                    $payload['perfil']        = 'morador';
                    $payload['tipo']          = 'morador';
                    $payload['condominio_id'] = condominioIdSessao();
                    // Admin_condominio não pode alterar CPF de terceiros:
                    unset($payload['cpf']);
                }
                $this->usuarioModel->update($usuario['id'], $payload);
                flashMessage('Usuário atualizado com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->usuario_editar id=' . $id);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $condominios = isSuperAdmin() ? $this->condominioModel->getAll(1) : [];
        $this->render('usuario_form', compact('usuario', 'condominios'));
    }

    public function usuario_excluir($id)
    {
        $fallback = base_url('?route=admin/usuarios');
        $usuario = $this->usuarioModel->getById((int)$id);
        if (!$usuario) {
            flashMessage('Usuário não encontrado.', 'error');
            redirect($fallback);
        }
        if (!isSuperAdmin()) {
            $cidSessao = (int)condominioIdSessao();
            if ((int)($usuario['condominio_id'] ?? 0) !== $cidSessao) {
                flashMessage('Você não tem permissão para excluir este usuário.', 'error');
                redirect($fallback);
            }
            $perfil = (string)($usuario['perfil'] ?? $usuario['tipo']);
            if (in_array($perfil, ['super_admin', 'admin', 'admin_condominio'], true)) {
                flashMessage('Somente o Super Administrador pode remover contas de gestores.', 'error');
                redirect($fallback);
            }
        } else {
            $perfil = (string)($usuario['perfil'] ?? $usuario['tipo']);
            if (in_array($perfil, ['super_admin', 'admin'], true)) {
                flashMessage('Contas de Super Administrador não podem ser removidas.', 'error');
                redirect($fallback);
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(true, $fallback);
        try {
            $this->usuarioModel->delete($usuario['id']);
            flashMessage('Usuário removido com sucesso!', 'success');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'Admin->usuario_excluir id=' . $id);
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }
        redirect($fallback);
    }

    // ========================= PAYLOADS VALIDADOS =========================
    // Monta array APENAS com colunas seguras e bem-formadas.
    // Nunca recebe $_POST inteiro. É aqui que impedimos mass assignment.
    // =======================================================================
    private function payloadCondominio(array $src): array
    {
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

    private function payloadUsuarioCriar(array $src): array
    {
        $payload = [
            'nome'          => trim((string)($src['nome'] ?? '')),
            'cpf'           => $src['cpf'] ?? '',
            'email'         => $src['email'] ?? null,
            'telefone'      => $src['telefone'] ?? null,
            'tipo'          => $src['tipo']   ?? 'morador',
            'perfil'        => $src['perfil'] ?? 'morador',
            'condominio_id' => !empty($src['condominio_id']) ? (int)$src['condominio_id'] : null,
            'senha'         => !empty($src['senha']) ? (string)$src['senha'] : null,
            'ativo'         => isset($src['ativo']) ? (int)$src['ativo'] : 1,
        ];
        if ($payload['tipo'] === 'admin') $payload['perfil'] = 'super_admin';
        return $payload;
    }

    private function payloadUsuarioAtualizar(array $src): array
    {
        $payload = $this->payloadUsuarioCriar($src);
        // Não obrigatório na edição: se campo senha vazio, não envia para update
        // (Model UsuarioModel só atualiza a senha se !empty).
        if (empty($src['senha'])) unset($payload['senha']);
        // Na edição o CPF é enviado SOMENTE se vier preenchido.
        if (empty($src['cpf'])) unset($payload['cpf']);
        return $payload;
    }

    // ========= ALIASES DE ROTAS (compatibilidade com URLs antigas) =========
    public function unidade_novo()    { $args = func_get_args();  $this->unidade_nova(...$args); }
    public function procuracao_novo() { $args = func_get_args();  $this->procuracao_nova(...$args); }
    public function assembleia_novo() { $args = func_get_args();  $this->assembleia_nova(...$args); }
    public function pauta_novo()      { $args = func_get_args();  $this->pauta_nova(...$args); }
    public function chapa_novo()      { $args = func_get_args();  $this->chapa_nova(...$args); }

    // =======================================================================
    // 🏢 ROTAS DO TENANT (disponíveis para admin_condominio / super_admin)
    // Quando chamado por admin_condominio, aplica tenant_guard automaticamente.
    // =======================================================================
    private function aplicarTenantGuard(?int $condominioId): void
    {
        if ($condominioId === null) return;
        if ((int)$condominioId <= 0) return;
        tenant_guard($condominioId);
    }

    public function pauta_desativar($id)
    {
        $fallback = base_url('?route=admin/index');
        $pauta = $this->pautaModel->getById($id);
        if (!$pauta) { flashMessage('Pauta não encontrada.', 'error'); redirect($fallback); }
        $a = $this->assembleiaModel->getById($pauta['assembleia_id']);
        if ($a) $this->aplicarTenantGuard((int)$a['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(false, $fallback);
        $this->pautaModel->setStatus($id, 'Pendente');
        flashMessage('Pauta retirada de votação e marcada como Pendente.', 'success');
        redirect(base_url('?route=admin/assembleia_gerenciar/' . (int)$pauta['assembleia_id']));
    }

    public function unidades()
    {
        $cid = condominioIdSessao();
        $unidades = $this->unidadeModel->getAll($cid);
        $this->render('unidades', compact('unidades'));
    }
    public function unidade_nova()
    {
        $fallback = base_url('?route=admin/unidades');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = $this->payloadUnidade($_POST);
                if (!isSuperAdmin()) $payload['condominio_id'] = condominioIdSessao();
                $this->unidadeModel->create($payload);
                flashMessage('Unidade cadastrada com sucesso!', 'success');
                redirect($fallback);
            } catch (PDOException $e) {
                $ticket = security_log_exception($e, 'Admin->unidade_nova');
                $msg = stripos($e->getMessage(), 'Duplicate') !== false
                    ? 'Erro: Combinação Condomínio / Lote / Casa já existe.'
                    : 'Não foi possível cadastrar a unidade.';
                flashMessage($msg . ' (' . sanitize($ticket) . ')', 'error');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->unidade_nova');
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $condominios = isSuperAdmin() ? $this->condominioModel->getAll(1) : [];
        $moradores = isSuperAdmin()
            ? $this->usuarioModel->getAll(null, 1, 'morador')
            : $this->usuarioModel->getAll(null, 1, 'morador', condominioIdSessao());
        $this->render('unidade_form', ['unidade' => null, 'condominios' => $condominios, 'moradores' => $moradores]);
    }
    public function unidade_editar($id)
    {
        $fallback = base_url('?route=admin/unidades');
        $unidade = $this->unidadeModel->getById($id);
        if (!$unidade) { flashMessage('Unidade não encontrada.', 'error'); redirect($fallback); }
        $this->aplicarTenantGuard((int)$unidade['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = $this->payloadUnidade($_POST);
                if (!isSuperAdmin()) $payload['condominio_id'] = condominioIdSessao();
                $this->unidadeModel->update($unidade['id'], $payload);
                flashMessage('Unidade atualizada com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->unidade_editar id=' . $id);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $condominios = isSuperAdmin() ? $this->condominioModel->getAll(1) : [];
        $moradores = isSuperAdmin()
            ? $this->usuarioModel->getAll(null, 1, 'morador')
            : $this->usuarioModel->getAll(null, 1, 'morador', condominioIdSessao());
        $this->render('unidade_form', compact('unidade', 'condominios', 'moradores'));
    }
    public function unidade_excluir($id)
    {
        $fallback = base_url('?route=admin/unidades');
        $unidade = $this->unidadeModel->getById($id);
        if (!$unidade) { flashMessage('Unidade não encontrada.', 'error'); redirect($fallback); }
        $this->aplicarTenantGuard((int)$unidade['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(true, $fallback);
        try {
            $this->unidadeModel->delete((int)$id);
            flashMessage('Unidade removida com sucesso!', 'success');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'Admin->unidade_excluir id=' . $id);
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }
        redirect($fallback);
    }
    private function payloadUnidade(array $src): array
    {
        return [
            'condominio_id' => !empty($src['condominio_id']) ? (int)$src['condominio_id'] : null,
            'lote'          => trim((string)($src['lote'] ?? '')),
            'casa'          => trim((string)($src['casa'] ?? '')),
            'bloco'         => $src['bloco'] ?? null,
            'usuario_id'    => !empty($src['usuario_id']) ? (int)$src['usuario_id'] : null,
            'ativo'         => isset($src['ativo']) ? (int)$src['ativo'] : 1,
        ];
    }

    public function procuracoes()
    {
        $cid = condominioIdSessao();
        $procuracoes = $this->procuracaoModel->getAll($cid);
        $this->render('procuracoes', compact('procuracoes'));
    }
    public function procuracao_nova()
    {
        $fallback = base_url('?route=admin/procuracoes');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $unidadeId = (int)($_POST['unidade_id'] ?? 0);
                $repId     = (int)($_POST['representante_id'] ?? 0);
                if ($this->procuracaoModel->exists($unidadeId, $repId)) {
                    flashMessage('Erro: Procuração já cadastrada para esta unidade e representante.', 'error');
                    redirect($fallback);
                }
                $payload = [
                    'unidade_id'       => $unidadeId,
                    'representante_id' => $repId,
                    'data_validade'    => $_POST['data_validade'] ?? null,
                    'ativo'            => isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1,
                ];
                if (!isSuperAdmin()) {
                    // Valida que a unidade pertence ao tenant do admin_condominio
                    $u = $this->unidadeModel->getById($unidadeId);
                    if (!$u || (int)$u['condominio_id'] !== (int)condominioIdSessao()) {
                        flashMessage('Unidade não pertence ao seu condomínio.', 'error');
                        redirect($fallback);
                    }
                }
                $this->procuracaoModel->create($payload);
                flashMessage('Procuração cadastrada com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->procuracao_nova');
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $cid = condominioIdSessao();
        $unidades = isSuperAdmin() ? $this->unidadeModel->getAll(null, 1) : $this->unidadeModel->getAll($cid, 1);
        $moradores = isSuperAdmin()
            ? $this->usuarioModel->getAll(null, 1, 'morador')
            : $this->usuarioModel->getAll(null, 1, 'morador', $cid);
        $this->render('procuracao_form', ['procuracao' => null, 'unidades' => $unidades, 'moradores' => $moradores]);
    }
    public function procuracao_editar($id)
    {
        $fallback = base_url('?route=admin/procuracoes');
        $procuracao = $this->procuracaoModel->getById($id);
        if (!$procuracao) { flashMessage('Procuração não encontrada.', 'error'); redirect($fallback); }
        if (!isSuperAdmin()) {
            $u = $this->unidadeModel->getById($procuracao['unidade_id']);
            if (!$u || (int)$u['condominio_id'] !== (int)condominioIdSessao()) {
                flashMessage('Você não tem permissão para editar esta procuração.', 'error');
                redirect($fallback);
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $unidadeId = (int)($_POST['unidade_id'] ?? 0);
                $repId     = (int)($_POST['representante_id'] ?? 0);
                if ($this->procuracaoModel->exists($unidadeId, $repId, (int)$id)) {
                    flashMessage('Erro: Procuração já cadastrada para esta unidade e representante.', 'error');
                    redirect($fallback);
                }
                $payload = [
                    'unidade_id'       => $unidadeId,
                    'representante_id' => $repId,
                    'data_validade'    => $_POST['data_validade'] ?? null,
                    'ativo'            => isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1,
                ];
                $this->procuracaoModel->update((int)$id, $payload);
                flashMessage('Procuração atualizada com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->procuracao_editar id=' . $id);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $cid = condominioIdSessao();
        $unidades  = isSuperAdmin() ? $this->unidadeModel->getAll(null, 1) : $this->unidadeModel->getAll($cid, 1);
        $moradores = isSuperAdmin()
            ? $this->usuarioModel->getAll(null, 1, 'morador')
            : $this->usuarioModel->getAll(null, 1, 'morador', $cid);
        $this->render('procuracao_form', compact('procuracao', 'unidades', 'moradores'));
    }
    public function procuracao_excluir($id)
    {
        $fallback = base_url('?route=admin/procuracoes');
        $procuracao = $this->procuracaoModel->getById($id);
        if (!$procuracao) { flashMessage('Procuração não encontrada.', 'error'); redirect($fallback); }
        if (!isSuperAdmin()) {
            $u = $this->unidadeModel->getById($procuracao['unidade_id']);
            if (!$u || (int)$u['condominio_id'] !== (int)condominioIdSessao()) {
                flashMessage('Você não tem permissão para excluir esta procuração.', 'error');
                redirect($fallback);
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(true, $fallback);
        try {
            $this->procuracaoModel->delete((int)$id);
            flashMessage('Procuração removida com sucesso!', 'success');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'Admin->procuracao_excluir id=' . $id);
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }
        redirect($fallback);
    }

    public function assembleias()
    {
        $assembleias = $this->assembleiaModel->getAll(condominioIdSessao());
        $this->render('assembleias', compact('assembleias'));
    }
    public function assembleia_nova()
    {
        $fallback = base_url('?route=admin/assembleias');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = $this->payloadAssembleia($_POST);
                if (!isSuperAdmin()) $payload['condominio_id'] = condominioIdSessao();
                $this->assembleiaModel->create($payload);
                flashMessage('Assembleia cadastrada com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->assembleia_nova');
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $condominios = isSuperAdmin() ? $this->condominioModel->getAll(1) : [];
        $this->render('assembleia_form', ['assembleia' => null, 'condominios' => $condominios]);
    }
    public function assembleia_editar($id)
    {
        $fallback = base_url('?route=admin/assembleias');
        $assembleia = $this->assembleiaModel->getById($id);
        if (!$assembleia) { flashMessage('Assembleia não encontrada.', 'error'); redirect($fallback); }
        $this->aplicarTenantGuard((int)$assembleia['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = $this->payloadAssembleia($_POST);
                if (!isSuperAdmin()) $payload['condominio_id'] = condominioIdSessao();
                $this->assembleiaModel->update($assembleia['id'], $payload);
                flashMessage('Assembleia atualizada com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->assembleia_editar id=' . $id);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $condominios = isSuperAdmin() ? $this->condominioModel->getAll(1) : [];
        $this->render('assembleia_form', compact('assembleia', 'condominios'));
    }
    public function assembleia_excluir($id)
    {
        $fallback = base_url('?route=admin/assembleias');
        $assembleia = $this->assembleiaModel->getById($id);
        if (!$assembleia) { flashMessage('Assembleia não encontrada.', 'error'); redirect($fallback); }
        $this->aplicarTenantGuard((int)$assembleia['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(true, $fallback);
        try {
            $this->assembleiaModel->delete((int)$id);
            flashMessage('Assembleia removida com sucesso!', 'success');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'Admin->assembleia_excluir id=' . $id);
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }
        redirect($fallback);
    }
    private function payloadAssembleia(array $src): array
    {
        return [
            'condominio_id' => !empty($src['condominio_id']) ? (int)$src['condominio_id'] : null,
            'titulo'        => trim((string)($src['titulo'] ?? '')),
            'data_assembleia' => $src['data_assembleia'] ?? null,
            'local'         => $src['local'] ?? null,
            'status'        => !empty($src['status']) ? (string)$src['status'] : 'Aberta',
            'tipo'          => $src['tipo'] ?? null,
            'quorum_minimo' => !empty($src['quorum_minimo']) ? (int)$src['quorum_minimo'] : 50,
        ];
    }

    public function assembleia_gerenciar($id)
    {
        $fallback = base_url('?route=admin/index');
        $assembleia = $this->assembleiaModel->getById($id);
        if (!$assembleia) { flashMessage('Assembleia não encontrada.', 'error'); redirect($fallback); }
        $this->aplicarTenantGuard((int)$assembleia['condominio_id']);
        $pautas          = $this->pautaModel->getAll($id);
        $chapas          = $this->chapaModel->getAll($id);
        $presencas       = $this->assembleiaModel->getPresencas($id);
        $countPresencas  = $this->assembleiaModel->countPresencas($id);
        $resultadoChapas = $this->chapaModel->getResultadoAssembleia($id);
        $this->render('assembleia_gerenciar', compact('assembleia', 'pautas', 'chapas', 'presencas', 'countPresencas', 'resultadoChapas'));
    }

    public function assembleia_abrir($id)
    {
        $fallback = base_url('?route=admin/index');
        $a = $this->assembleiaModel->getById($id);
        if (!$a) { flashMessage('Assembleia não encontrada.', 'error'); redirect($fallback); }
        $this->aplicarTenantGuard((int)$a['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(false, $fallback);
        $this->assembleiaModel->setStatus($id, 'Aberta');
        flashMessage('Assembleia aberta para votação!', 'success');
        redirect(base_url('?route=admin/assembleia_gerenciar/' . (int)$id));
    }
    public function assembleia_fechar($id)
    {
        $fallback = base_url('?route=admin/index');
        $a = $this->assembleiaModel->getById($id);
        if (!$a) { flashMessage('Assembleia não encontrada.', 'error'); redirect($fallback); }
        $this->aplicarTenantGuard((int)$a['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(false, $fallback);
        $this->assembleiaModel->setStatus($id, 'Fechada');
        $pautas = $this->pautaModel->getAll($id);
        foreach ($pautas as $pauta) {
            if ((int)($pauta['total_votos'] ?? 0) > 0) $this->pautaModel->atualizarResultado($pauta['id']);
        }
        flashMessage('Assembleia fechada e resultados apurados!', 'success');
        redirect(base_url('?route=admin/assembleia_gerenciar/' . (int)$id));
    }

    public function pauta_nova($assembleiaId)
    {
        $fallback = base_url('?route=admin/assembleia_gerenciar/' . (int)$assembleiaId);
        $assembleia = $this->assembleiaModel->getById($assembleiaId);
        if (!$assembleia) { flashMessage('Assembleia não encontrada.', 'error'); redirect($fallback); }
        $this->aplicarTenantGuard((int)$assembleia['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = [
                    'assembleia_id' => (int)$assembleiaId,
                    'titulo'        => trim((string)($_POST['titulo'] ?? '')),
                    'descricao'     => $_POST['descricao'] ?? null,
                    'status'        => !empty($_POST['status']) ? (string)$_POST['status'] : 'Pendente',
                ];
                $this->pautaModel->create($payload);
                flashMessage('Pauta cadastrada com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->pauta_nova assembleia=' . $assembleiaId);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $this->render('pauta_form', ['pauta' => null, 'assembleia' => $assembleia]);
    }
    public function pauta_editar($id)
    {
        $pauta = $this->pautaModel->getById($id);
        if (!$pauta) { flashMessage('Pauta não encontrada.', 'error'); redirect(base_url('?route=admin/index')); }
        $fallback = base_url('?route=admin/assembleia_gerenciar/' . (int)$pauta['assembleia_id']);
        $assembleia = $this->assembleiaModel->getById($pauta['assembleia_id']);
        if ($assembleia) $this->aplicarTenantGuard((int)$assembleia['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $payload = [
                    'assembleia_id' => (int)$pauta['assembleia_id'],
                    'titulo'        => trim((string)($_POST['titulo'] ?? '')),
                    'descricao'     => $_POST['descricao'] ?? null,
                    'status'        => !empty($_POST['status']) ? (string)$_POST['status'] : 'Pendente',
                ];
                $this->pautaModel->update((int)$id, $payload);
                flashMessage('Pauta atualizada com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->pauta_editar id=' . $id);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $this->render('pauta_form', compact('pauta', 'assembleia'));
    }
    public function pauta_excluir($id)
    {
        $pauta = $this->pautaModel->getById($id);
        if (!$pauta) { flashMessage('Pauta não encontrada.', 'error'); redirect(base_url('?route=admin/index')); }
        $fallback = base_url('?route=admin/assembleia_gerenciar/' . (int)$pauta['assembleia_id']);
        $a = $this->assembleiaModel->getById($pauta['assembleia_id']);
        if ($a) $this->aplicarTenantGuard((int)$a['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(true, $fallback);
        try {
            $this->pautaModel->delete((int)$id);
            flashMessage('Pauta removida com sucesso!', 'success');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'Admin->pauta_excluir id=' . $id);
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }
        redirect($fallback);
    }

    public function pauta_ativar($id)
    {
        $pauta = $this->pautaModel->getById($id);
        if (!$pauta) { flashMessage('Pauta não encontrada.', 'error'); redirect(base_url('?route=admin/index')); }
        $fallback = base_url('?route=admin/assembleia_gerenciar/' . (int)$pauta['assembleia_id']);
        $a = $this->assembleiaModel->getById($pauta['assembleia_id']);
        if ($a) $this->aplicarTenantGuard((int)$a['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(false, $fallback);
        $pautas = $this->pautaModel->getAll($pauta['assembleia_id']);
        foreach ($pautas as $p) {
            if (($p['status'] ?? '') === 'Em votação') $this->pautaModel->setStatus($p['id'], 'Pendente');
        }
        $this->pautaModel->setStatus($id, 'Em votação');
        flashMessage('Pauta em votação!', 'success');
        redirect($fallback);
    }

    public function chapa_nova($assembleiaId)
    {
        $fallback = base_url('?route=admin/assembleia_gerenciar/' . (int)$assembleiaId);
        $assembleia = $this->assembleiaModel->getById($assembleiaId);
        if (!$assembleia) { flashMessage('Assembleia não encontrada.', 'error'); redirect(base_url('?route=admin/index')); }
        $this->aplicarTenantGuard((int)$assembleia['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $this->chapaModel->create([
                    'assembleia_id' => (int)$assembleiaId,
                    'nome'          => trim((string)($_POST['nome'] ?? '')),
                    'cargo'         => $_POST['cargo'] ?? null,
                    'representante_nome'  => $_POST['representante_nome'] ?? null,
                    'representante_cargo' => $_POST['representante_cargo'] ?? null,
                    'status'        => !empty($_POST['status']) ? (string)$_POST['status'] : 'Ativa',
                ]);
                flashMessage('Chapa cadastrada com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->chapa_nova assembleia=' . $assembleiaId);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $this->render('chapa_form', ['chapa' => null, 'assembleia' => $assembleia]);
    }
    public function chapa_editar($id)
    {
        $chapa = $this->chapaModel->getById($id);
        if (!$chapa) { flashMessage('Chapa não encontrada.', 'error'); redirect(base_url('?route=admin/index')); }
        $fallback = base_url('?route=admin/assembleia_gerenciar/' . (int)$chapa['assembleia_id']);
        $assembleia = $this->assembleiaModel->getById($chapa['assembleia_id']);
        if ($assembleia) $this->aplicarTenantGuard((int)$assembleia['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_token_verify(true, $fallback);
            try {
                $this->chapaModel->update((int)$id, [
                    'assembleia_id' => (int)$chapa['assembleia_id'],
                    'nome'          => trim((string)($_POST['nome'] ?? '')),
                    'cargo'         => $_POST['cargo'] ?? null,
                    'representante_nome'  => $_POST['representante_nome'] ?? null,
                    'representante_cargo' => $_POST['representante_cargo'] ?? null,
                    'status'        => !empty($_POST['status']) ? (string)$_POST['status'] : 'Ativa',
                ]);
                flashMessage('Chapa atualizada com sucesso!', 'success');
                redirect($fallback);
            } catch (Throwable $e) {
                $ticket = security_log_exception($e, 'Admin->chapa_editar id=' . $id);
                flashMessage(security_mensagem_amigavel($ticket), 'error');
                redirect($fallback);
            }
        }
        $this->render('chapa_form', compact('chapa', 'assembleia'));
    }
    public function chapa_excluir($id)
    {
        $chapa = $this->chapaModel->getById($id);
        if (!$chapa) { flashMessage('Chapa não encontrada.', 'error'); redirect(base_url('?route=admin/index')); }
        $fallback = base_url('?route=admin/assembleia_gerenciar/' . (int)$chapa['assembleia_id']);
        $a = $this->assembleiaModel->getById($chapa['assembleia_id']);
        if ($a) $this->aplicarTenantGuard((int)$a['condominio_id']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_token_verify(true, $fallback);
        try {
            $this->chapaModel->delete((int)$id);
            flashMessage('Chapa removida com sucesso!', 'success');
        } catch (Throwable $e) {
            $ticket = security_log_exception($e, 'Admin->chapa_excluir id=' . $id);
            flashMessage(security_mensagem_amigavel($ticket), 'error');
        }
        redirect($fallback);
    }
}
