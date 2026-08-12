<?php

class AssembleiaController
{
    private $assembleiaModel;
    private $unidadeModel;
    private $procuracaoModel;
    private $pautaModel;
    private $chapaModel;

    public function __construct()
    {
        requireLogin();
        $this->assembleiaModel  = new AssembleiaModel();
        $this->unidadeModel     = new UnidadeModel();
        $this->procuracaoModel  = new ProcuracaoModel();
        $this->pautaModel       = new PautaModel();
        $this->chapaModel       = new ChapaModel();
    }

    private function render($view, $data = [])
    {
        $data['flash'] = flashMessage();
        extract($data);
        require __DIR__ . '/../Views/Layouts/header_morador.php';
        require __DIR__ . '/../Views/Assembleia/' . $view . '.php';
        require __DIR__ . '/../Views/Layouts/footer_morador.php';
    }

    public function index()
    {
        $usuarioId = $_SESSION['usuario_id'];
        $condominiosUsuario = $this->unidadeModel->getCondominiosDoUsuario($usuarioId);
        $assembleiasAbertas = $this->assembleiaModel->getAbertaParaUsuario($usuarioId);
        $assembleiasGeral = [];
        foreach ($condominiosUsuario as $cond) {
            $assemb = $this->assembleiaModel->getAll($cond['id']);
            $assembleiasGeral = array_merge($assembleiasGeral, $assemb);
        }
        $this->render('lista', compact('condominiosUsuario', 'assembleiasAbertas', 'assembleiasGeral'));
    }

    public function ver($id)
    {
        $usuarioId = $_SESSION['usuario_id'];
        $assembleia = $this->assembleiaModel->getById($id);

        if (!$assembleia) {
            flashMessage('Assembleia não encontrada.', 'error');
            redirect(base_url('?route=assembleia/index'));
        }

        $condominioId = $assembleia['condominio_id'];
        $unidades = $this->procuracaoModel->getTodasUnidadesUsuario($usuarioId, $condominioId);

        if (empty($unidades) && !isAdmin()) {
            flashMessage('Você não tem permissão para acessar esta assembleia.', 'error');
            redirect(base_url('?route=assembleia/index'));
        }

        foreach ($unidades as $unidade) {
            if (!$this->assembleiaModel->hasPresenca($id, $unidade['id'])) {
                $this->assembleiaModel->registrarPresenca(
                    $id,
                    $unidade['id'],
                    $usuarioId,
                    (int) $unidade['via_procuracao'],
                    !empty($unidade['procuracao_id']) ? $unidade['procuracao_id'] : null
                );
            }
        }

        $pautas = $this->pautaModel->getAll($id);
        $chapas = $this->chapaModel->getAll($id);
        $votosPautasUsuario = [];
        foreach ($pautas as $pauta) {
            $votosPautasUsuario[$pauta['id']] = [];
            foreach ($unidades as $un) {
                if ($this->pautaModel->hasVoto($pauta['id'], $un['id'])) {
                    $votosPautasUsuario[$pauta['id']][] = $un['id'];
                }
            }
        }
        $votosChapasUsuario = [];
        foreach ($unidades as $un) {
            $votosChapasUsuario[$un['id']] = $this->chapaModel->hasVotoNaAssembleia($id, $un['id']);
        }
        $presencasCount = $this->assembleiaModel->countPresencas($id);
        $resultadoChapas = $this->chapaModel->getResultadoAssembleia($id);

        $this->render('ver', compact(
            'assembleia',
            'unidades',
            'pautas',
            'chapas',
            'votosPautasUsuario',
            'votosChapasUsuario',
            'presencasCount',
            'resultadoChapas'
        ));
    }

    public function votar_pauta($pautaId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('?route=assembleia/index'));
        }

        $pauta = $this->pautaModel->getById($pautaId);
        if (!$pauta) {
            flashMessage('Pauta não encontrada.', 'error');
            redirect(base_url('?route=assembleia/index'));
        }

        $usuarioId = $_SESSION['usuario_id'];
        $assembleiaId = $pauta['assembleia_id'];

        $unidadeId = (int) ($_POST['unidade_id'] ?? 0);
        $voto = $_POST['voto'] ?? '';

        if (!in_array($voto, ['Sim', 'Não'])) {
            flashMessage('Voto inválido.', 'error');
            redirect(base_url('?route=assembleia/ver/' . $assembleiaId));
        }

        $unidades = $this->procuracaoModel->getTodasUnidadesUsuario($usuarioId);
        $unidadeSelecionada = null;
        foreach ($unidades as $un) {
            if ($un['id'] === $unidadeId) {
                $unidadeSelecionada = $un;
                break;
            }
        }

        if (!$unidadeSelecionada) {
            flashMessage('Unidade inválida ou não pertence a você.', 'error');
            redirect(base_url('?route=assembleia/ver/' . $assembleiaId));
        }

        if ($this->pautaModel->hasVoto($pautaId, $unidadeId)) {
            flashMessage('Esta unidade já votou nesta pauta.', 'error');
            redirect(base_url('?route=assembleia/ver/' . $assembleiaId));
        }

        $resultado = $this->pautaModel->votar(
            $pautaId,
            $unidadeId,
            $usuarioId,
            $voto,
            (int) $unidadeSelecionada['via_procuracao'],
            !empty($unidadeSelecionada['procuracao_id']) ? $unidadeSelecionada['procuracao_id'] : null
        );

        if ($resultado === null) {
            flashMessage('Voto já registrado anteriormente para esta unidade.', 'error');
        } else {
            flashMessage('Voto registrado com sucesso: ' . $voto . ' (Unidade: Lote ' . $unidadeSelecionada['lote'] . ' / Casa ' . $unidadeSelecionada['casa'] . ')', 'success');
        }

        redirect(base_url('?route=assembleia/ver/' . $assembleiaId));
    }

    public function votar_chapa($chapaId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('?route=assembleia/index'));
        }

        $chapa = $this->chapaModel->getById($chapaId);
        if (!$chapa) {
            flashMessage('Chapa não encontrada.', 'error');
            redirect(base_url('?route=assembleia/index'));
        }

        $usuarioId = $_SESSION['usuario_id'];
        $assembleiaId = $chapa['assembleia_id'];
        $unidadeId = (int) ($_POST['unidade_id'] ?? 0);

        $unidades = $this->procuracaoModel->getTodasUnidadesUsuario($usuarioId);
        $unidadeSelecionada = null;
        foreach ($unidades as $un) {
            if ($un['id'] === $unidadeId) {
                $unidadeSelecionada = $un;
                break;
            }
        }

        if (!$unidadeSelecionada) {
            flashMessage('Unidade inválida ou não pertence a você.', 'error');
            redirect(base_url('?route=assembleia/ver/' . $assembleiaId));
        }

        if ($this->chapaModel->hasVotoNaAssembleia($assembleiaId, $unidadeId)) {
            flashMessage('Esta unidade já votou para diretoria nesta assembleia.', 'error');
            redirect(base_url('?route=assembleia/ver/' . $assembleiaId));
        }

        $resultado = $this->chapaModel->votar(
            $chapaId,
            $unidadeId,
            $usuarioId,
            (int) $unidadeSelecionada['via_procuracao'],
            !empty($unidadeSelecionada['procuracao_id']) ? $unidadeSelecionada['procuracao_id'] : null
        );

        if ($resultado === null) {
            flashMessage('Voto já registrado anteriormente para esta unidade.', 'error');
        } else {
            flashMessage('Voto registrado na chapa: ' . $chapa['nome_chapa'] . ' (Unidade: Lote ' . $unidadeSelecionada['lote'] . ' / Casa ' . $unidadeSelecionada['casa'] . ')', 'success');
        }

        redirect(base_url('?route=assembleia/ver/' . $assembleiaId));
    }
}
