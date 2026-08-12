<?php

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
        extract($data);
        require __DIR__ . '/../Views/Layouts/header_admin.php';
        require __DIR__ . '/../Views/Admin/' . $view . '.php';
        require __DIR__ . '/../Views/Layouts/footer_admin.php';
    }

    public function index()
    {
        $stats = [
            'total_condominios'  => $this->condominioModel->count(),
            'total_usuarios'     => count($this->usuarioModel->getAll(null, 1)),
            'total_unidades'     => $this->unidadeModel->count(),
            'total_assembleias'  => count($this->assembleiaModel->getAll()),
            'assembleias_abertas'=> count($this->assembleiaModel->getAll(null, 'Aberta')),
        ];
        $ultimasAssembleias = $this->assembleiaModel->getAll();
        $this->render('dashboard', compact('stats', 'ultimasAssembleias'));
    }

    public function condominios()
    {
        $condominios = $this->condominioModel->getAll();
        $this->render('condominios', compact('condominios'));
    }

    public function condominio_novo()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->condominioModel->create($_POST);
            flashMessage('Condomínio cadastrado com sucesso!', 'success');
            redirect(base_url('?route=admin/condominios'));
        }
        $this->render('condominio_form', ['condominio' => null]);
    }

    public function condominio_editar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->condominioModel->update($id, $_POST);
            flashMessage('Condomínio atualizado com sucesso!', 'success');
            redirect(base_url('?route=admin/condominios'));
        }
        $condominio = $this->condominioModel->getById($id);
        $this->render('condominio_form', compact('condominio'));
    }

    public function condominio_excluir($id)
    {
        $this->condominioModel->delete($id);
        flashMessage('Condomínio removido com sucesso!', 'success');
        redirect(base_url('?route=admin/condominios'));
    }

    public function usuarios()
    {
        $usuarios = $this->usuarioModel->getAll();
        $this->render('usuarios', compact('usuarios'));
    }

    public function usuario_novo()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->usuarioModel->create($_POST);
                flashMessage('Usuário cadastrado com sucesso!', 'success');
                redirect(base_url('?route=admin/usuarios'));
            } catch (PDOException $e) {
                flashMessage('Erro: CPF já cadastrado.', 'error');
            }
        }
        $this->render('usuario_form', ['usuario' => null]);
    }

    public function usuario_editar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->usuarioModel->update($id, $_POST);
            flashMessage('Usuário atualizado com sucesso!', 'success');
            redirect(base_url('?route=admin/usuarios'));
        }
        $usuario = $this->usuarioModel->getById($id);
        $this->render('usuario_form', compact('usuario'));
    }

    public function usuario_excluir($id)
    {
        $this->usuarioModel->delete($id);
        flashMessage('Usuário removido com sucesso!', 'success');
        redirect(base_url('?route=admin/usuarios'));
    }

    public function unidades()
    {
        $unidades = $this->unidadeModel->getAll();
        $this->render('unidades', compact('unidades'));
    }

    public function unidade_nova()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->unidadeModel->create($_POST);
                flashMessage('Unidade cadastrada com sucesso!', 'success');
                redirect(base_url('?route=admin/unidades'));
            } catch (PDOException $e) {
                flashMessage('Erro: Combinação de Condomínio/Lote/Casa já existe.', 'error');
            }
        }
        $condominios = $this->condominioModel->getAll(1);
        $moradores = $this->usuarioModel->getAll('morador', 1);
        $this->render('unidade_form', ['unidade' => null, 'condominios' => $condominios, 'moradores' => $moradores]);
    }

    public function unidade_editar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->unidadeModel->update($id, $_POST);
            flashMessage('Unidade atualizada com sucesso!', 'success');
            redirect(base_url('?route=admin/unidades'));
        }
        $unidade = $this->unidadeModel->getById($id);
        $condominios = $this->condominioModel->getAll(1);
        $moradores = $this->usuarioModel->getAll('morador', 1);
        $this->render('unidade_form', compact('unidade', 'condominios', 'moradores'));
    }

    public function unidade_excluir($id)
    {
        $this->unidadeModel->delete($id);
        flashMessage('Unidade removida com sucesso!', 'success');
        redirect(base_url('?route=admin/unidades'));
    }

    public function procuracoes()
    {
        $procuracoes = $this->procuracaoModel->getAll();
        $this->render('procuracoes', compact('procuracoes'));
    }

    public function procuracao_nova()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->procuracaoModel->exists($_POST['unidade_id'], $_POST['representante_id'])) {
                flashMessage('Erro: Procuração já cadastrada para esta unidade e representante.', 'error');
            } else {
                $this->procuracaoModel->create($_POST);
                flashMessage('Procuração cadastrada com sucesso!', 'success');
                redirect(base_url('?route=admin/procuracoes'));
            }
        }
        $unidades = $this->unidadeModel->getAll(null, 1);
        $moradores = $this->usuarioModel->getAll('morador', 1);
        $this->render('procuracao_form', ['procuracao' => null, 'unidades' => $unidades, 'moradores' => $moradores]);
    }

    public function procuracao_editar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($this->procuracaoModel->exists($_POST['unidade_id'], $_POST['representante_id'], $id)) {
                flashMessage('Erro: Procuração já cadastrada para esta unidade e representante.', 'error');
            } else {
                $this->procuracaoModel->update($id, $_POST);
                flashMessage('Procuração atualizada com sucesso!', 'success');
                redirect(base_url('?route=admin/procuracoes'));
            }
        }
        $procuracao = $this->procuracaoModel->getById($id);
        $unidades = $this->unidadeModel->getAll(null, 1);
        $moradores = $this->usuarioModel->getAll('morador', 1);
        $this->render('procuracao_form', compact('procuracao', 'unidades', 'moradores'));
    }

    public function procuracao_excluir($id)
    {
        $this->procuracaoModel->delete($id);
        flashMessage('Procuração removida com sucesso!', 'success');
        redirect(base_url('?route=admin/procuracoes'));
    }

    public function assembleias()
    {
        $assembleias = $this->assembleiaModel->getAll();
        $this->render('assembleias', compact('assembleias'));
    }

    public function assembleia_nova()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assembleiaModel->create($_POST);
            flashMessage('Assembleia cadastrada com sucesso!', 'success');
            redirect(base_url('?route=admin/assembleias'));
        }
        $condominios = $this->condominioModel->getAll(1);
        $this->render('assembleia_form', ['assembleia' => null, 'condominios' => $condominios]);
    }

    public function assembleia_editar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->assembleiaModel->update($id, $_POST);
            flashMessage('Assembleia atualizada com sucesso!', 'success');
            redirect(base_url('?route=admin/assembleias'));
        }
        $assembleia = $this->assembleiaModel->getById($id);
        $condominios = $this->condominioModel->getAll(1);
        $this->render('assembleia_form', compact('assembleia', 'condominios'));
    }

    public function assembleia_excluir($id)
    {
        $this->assembleiaModel->delete($id);
        flashMessage('Assembleia removida com sucesso!', 'success');
        redirect(base_url('?route=admin/assembleias'));
    }

    public function assembleia_gerenciar($id)
    {
        $assembleia = $this->assembleiaModel->getById($id);
        $pautas = $this->pautaModel->getAll($id);
        $chapas = $this->chapaModel->getAll($id);
        $presencas = $this->assembleiaModel->getPresencas($id);
        $countPresencas = $this->assembleiaModel->countPresencas($id);
        $resultadoChapas = $this->chapaModel->getResultadoAssembleia($id);
        $this->render('assembleia_gerenciar', compact('assembleia', 'pautas', 'chapas', 'presencas', 'countPresencas', 'resultadoChapas'));
    }

    public function assembleia_abrir($id)
    {
        $this->assembleiaModel->setStatus($id, 'Aberta');
        flashMessage('Assembleia aberta para votação!', 'success');
        redirect(base_url('?route=admin/assembleia_gerenciar/' . $id));
    }

    public function assembleia_fechar($id)
    {
        $this->assembleiaModel->setStatus($id, 'Fechada');
        $pautas = $this->pautaModel->getAll($id);
        foreach ($pautas as $pauta) {
            if ($pauta['total_votos'] > 0) {
                $this->pautaModel->atualizarResultado($pauta['id']);
            }
        }
        flashMessage('Assembleia fechada e resultados apurados!', 'success');
        redirect(base_url('?route=admin/assembleia_gerenciar/' . $id));
    }

    public function pauta_nova($assembleiaId)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST['assembleia_id'] = $assembleiaId;
            $this->pautaModel->create($_POST);
            flashMessage('Pauta cadastrada com sucesso!', 'success');
            redirect(base_url('?route=admin/assembleia_gerenciar/' . $assembleiaId));
        }
        $assembleia = $this->assembleiaModel->getById($assembleiaId);
        $this->render('pauta_form', ['pauta' => null, 'assembleia' => $assembleia]);
    }

    public function pauta_editar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->pautaModel->update($id, $_POST);
            flashMessage('Pauta atualizada com sucesso!', 'success');
            redirect(base_url('?route=admin/assembleia_gerenciar/' . $_POST['assembleia_id']));
        }
        $pauta = $this->pautaModel->getById($id);
        $assembleia = $this->assembleiaModel->getById($pauta['assembleia_id']);
        $this->render('pauta_form', compact('pauta', 'assembleia'));
    }

    public function pauta_excluir($id)
    {
        $pauta = $this->pautaModel->getById($id);
        $this->pautaModel->delete($id);
        flashMessage('Pauta removida com sucesso!', 'success');
        redirect(base_url('?route=admin/assembleia_gerenciar/' . $pauta['assembleia_id']));
    }

    public function pauta_ativar($id)
    {
        $pauta = $this->pautaModel->getById($id);
        $pautas = $this->pautaModel->getAll($pauta['assembleia_id']);
        foreach ($pautas as $p) {
            if ($p['status'] === 'Em votação') {
                $this->pautaModel->setStatus($p['id'], 'Pendente');
            }
        }
        $this->pautaModel->setStatus($id, 'Em votação');
        flashMessage('Pauta em votação!', 'success');
        redirect(base_url('?route=admin/assembleia_gerenciar/' . $pauta['assembleia_id']));
    }

    public function chapa_nova($assembleiaId)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST['assembleia_id'] = $assembleiaId;
            $this->chapaModel->create($_POST);
            flashMessage('Chapa cadastrada com sucesso!', 'success');
            redirect(base_url('?route=admin/assembleia_gerenciar/' . $assembleiaId));
        }
        $assembleia = $this->assembleiaModel->getById($assembleiaId);
        $this->render('chapa_form', ['chapa' => null, 'assembleia' => $assembleia]);
    }

    public function chapa_editar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->chapaModel->update($id, $_POST);
            flashMessage('Chapa atualizada com sucesso!', 'success');
            redirect(base_url('?route=admin/assembleia_gerenciar/' . $_POST['assembleia_id']));
        }
        $chapa = $this->chapaModel->getById($id);
        $assembleia = $this->assembleiaModel->getById($chapa['assembleia_id']);
        $this->render('chapa_form', compact('chapa', 'assembleia'));
    }

    public function chapa_excluir($id)
    {
        $chapa = $this->chapaModel->getById($id);
        $this->chapaModel->delete($id);
        flashMessage('Chapa removida com sucesso!', 'success');
        redirect(base_url('?route=admin/assembleia_gerenciar/' . $chapa['assembleia_id']));
    }
}
