<?php

class RelatoriosController
{
    private $assembleiaModel;
    private $pautaModel;
    private $chapaModel;

    public function __construct()
    {
        rbac_require_login();
        $this->assembleiaModel  = new AssembleiaModel();
        $this->pautaModel       = new PautaModel();
        $this->chapaModel       = new ChapaModel();
    }

    public function presenca($id)
    {
        if (!rbac_is_admin()) {
            flashMessage('Acesso negado.', 'error');
            redirect(base_url('?route=assembleia/index'));
        }

        $assembleia = $this->assembleiaModel->getById($id);
        if (!$assembleia) {
            flashMessage('Assembleia não encontrada.', 'error');
            redirect(base_url('?route=admin/assembleias'));
        }

        $presencas = $this->assembleiaModel->getPresencas($id);
        $totalUnidades = 0;
        $unidadeModel = new UnidadeModel();
        $totalUnidades = $unidadeModel->count($assembleia['condominio_id']);

        $titulo = 'LISTA DE PRESENÇA';
        $subtitle = 'Assembleia ' . $assembleia['tipo'];

        extract(compact('assembleia', 'presencas', 'totalUnidades', 'titulo', 'subtitle'));
        require __DIR__ . '/../Views/Relatorios/presenca.php';
    }

    public function resultados($id)
    {
        if (!rbac_is_admin()) {
            flashMessage('Acesso negado.', 'error');
            redirect(base_url('?route=assembleia/index'));
        }

        $assembleia = $this->assembleiaModel->getById($id);
        if (!$assembleia) {
            flashMessage('Assembleia não encontrada.', 'error');
            redirect(base_url('?route=admin/assembleias'));
        }

        $pautas = $this->pautaModel->getAll($id);
        foreach ($pautas as &$pauta) {
            $pauta['detalhes_votos'] = $this->pautaModel->getVotos($pauta['id']);
        }
        $resultadoChapas = $this->chapaModel->getResultadoAssembleia($id);
        $chapas = $this->chapaModel->getAll($id);
        $detalhesVotosChapas = [];
        foreach ($chapas as $ch) {
            $detalhesVotosChapas[$ch['id']] = $this->chapaModel->getVotos($ch['id']);
        }

        $titulo = 'RELATÓRIO DE RESULTADOS';
        $subtitle = 'Assembleia ' . $assembleia['tipo'];

        extract(compact('assembleia', 'pautas', 'resultadoChapas', 'detalhesVotosChapas', 'titulo', 'subtitle'));
        require __DIR__ . '/../Views/Relatorios/resultados.php';
    }
}
