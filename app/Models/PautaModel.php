<?php

require_once __DIR__ . '/Database.php';

class PautaModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll($assembleiaId = null, $status = null)
    {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM votos_pautas vp WHERE vp.pauta_id = p.id AND vp.voto = 'Sim') as votos_sim,
                       (SELECT COUNT(*) FROM votos_pautas vp WHERE vp.pauta_id = p.id AND vp.voto = 'Não') as votos_nao,
                       (SELECT COUNT(*) FROM votos_pautas vp WHERE vp.pauta_id = p.id) as total_votos
                FROM pautas p
                WHERE 1=1";
        $params = [];
        if ($assembleiaId !== null) {
            $sql .= " AND p.assembleia_id = :assembleia_id";
            $params[':assembleia_id'] = $assembleiaId;
        }
        if ($status !== null) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $status;
        }
        $sql .= " ORDER BY p.ordem ASC, p.id ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById($id)
    {
        $sql = "SELECT p.*, a.titulo as assembleia_titulo, a.status as assembleia_status,
                       (SELECT COUNT(*) FROM votos_pautas vp WHERE vp.pauta_id = p.id AND vp.voto = 'Sim') as votos_sim,
                       (SELECT COUNT(*) FROM votos_pautas vp WHERE vp.pauta_id = p.id AND vp.voto = 'Não') as votos_nao,
                       (SELECT COUNT(*) FROM votos_pautas vp WHERE vp.pauta_id = p.id) as total_votos
                FROM pautas p
                INNER JOIN assembleias a ON p.assembleia_id = a.id
                WHERE p.id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    public function create($data)
    {
        return $this->db->insert('pautas', [
            'assembleia_id' => $data['assembleia_id'],
            'ordem'         => isset($data['ordem']) ? $data['ordem'] : 1,
            'titulo'        => $data['titulo'],
            'descricao'     => !empty($data['descricao']) ? $data['descricao'] : null,
            'status'        => isset($data['status']) ? $data['status'] : 'Pendente',
        ]);
    }

    public function update($id, $data)
    {
        return $this->db->update('pautas', [
            'assembleia_id' => $data['assembleia_id'],
            'ordem'         => isset($data['ordem']) ? $data['ordem'] : 1,
            'titulo'        => $data['titulo'],
            'descricao'     => !empty($data['descricao']) ? $data['descricao'] : null,
            'status'        => isset($data['status']) ? $data['status'] : 'Pendente',
        ], 'id = :id', [':id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete('pautas', 'id = :id', [':id' => $id]);
    }

    public function setStatus($id, $status)
    {
        return $this->db->update('pautas', [
            'status' => $status,
        ], 'id = :id', [':id' => $id]);
    }

    public function votar($pautaId, $unidadeId, $usuarioId, $voto, $viaProcuracao = 0, $procuracaoId = null)
    {
        try {
            return $this->db->insert('votos_pautas', [
                'pauta_id'           => $pautaId,
                'unidade_id'         => $unidadeId,
                'usuario_votante_id' => $usuarioId,
                'voto'               => $voto,
                'via_procuracao'     => $viaProcuracao,
                'procuracao_id'      => $procuracaoId,
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1062 Duplicate') !== false) {
                return null;
            }
            throw $e;
        }
    }

    public function hasVoto($pautaId, $unidadeId)
    {
        $sql = "SELECT COUNT(*) as qtd FROM votos_pautas WHERE pauta_id = :pauta_id AND unidade_id = :unidade_id";
        $row = $this->db->fetchOne($sql, [
            ':pauta_id'   => $pautaId,
            ':unidade_id' => $unidadeId,
        ]);
        return ($row['qtd'] ?? 0) > 0;
    }

    /**
     * Retorna RESULTADO DETALHADO por pauta + estatísticas gerais de quorum
     * para a assembleia. Usado na tela APURAÇÃO FINAL (status Encerrada).
     *
     * Retorno: [
     *   'pautas' => [
     *      0 => ['id','titulo','status','votos_sim','votos_nao','abstencoes','total_votos',
     *            'total_elegiveis','pct_sim','pct_nao','pct_abstencao','resultado' => 'Aprovada|Rejeitada|Empate']
     *      ...
     *   ],
     *   'quorum' => ['total_unidades_condominio' => X, 'total_presentes' => Y, 'pct_presenca' => Z]
     * ]
     */
    public function getResultadoApuracao(int $assembleiaId): array {
        $assembleiaId = (int)$assembleiaId;
        $params = [':aid' => $assembleiaId];

        // 1) Dados base da assembleia (condominio_id)
        $dadosAssembleia = $this->db->fetchOne(
            "SELECT condominio_id FROM assembleias WHERE id = :aid LIMIT 1",
            [':aid' => $assembleiaId]
        );
        $condominioId = (int)($dadosAssembleia['condominio_id'] ?? 0);

        // 2) Total de unidades elegíveis no condomínio (ativo=1)
        $totalElegiveis = (int)$this->db->fetchOne(
            "SELECT COUNT(*) AS q FROM unidades WHERE condominio_id = :cid AND ativo = 1",
            [':cid' => $condominioId]
        )['q'] ?? 0;

        // 3) Total de presenças ÚNICAS por unidade nesta assembleia
        //    (unidade que tem 1 linha em presencas_assembleia)
        $totalPresentes = (int)$this->db->fetchOne(
            "SELECT COUNT(DISTINCT pa.unidade_id) AS q
               FROM presencas_assembleia pa
               INNER JOIN unidades u ON pa.unidade_id = u.id
              WHERE pa.assembleia_id = :aid AND u.ativo = 1",
            [':aid' => $assembleiaId]
        )['q'] ?? 0;

        // 4) Pautas + votos apurados (mesmo calculo de getAll + abstenções explicitas)
        $sqlPautas = "SELECT
            p.id, p.titulo, p.status, p.descricao, p.ordem,
            COALESCE((SELECT COUNT(*) FROM votos_pautas vp WHERE vp.pauta_id = p.id AND vp.voto = 'Sim'), 0) as votos_sim,
            COALESCE((SELECT COUNT(*) FROM votos_pautas vp WHERE vp.pauta_id = p.id AND vp.voto = 'Não'), 0) as votos_nao,
            COALESCE((SELECT COUNT(*) FROM votos_pautas vp WHERE vp.pauta_id = p.id), 0)               as total_votos,
            :total_presentes AS total_presentes,
            :total_elegiveis AS total_elegiveis
        FROM pautas p
        WHERE p.assembleia_id = :aid
        ORDER BY p.ordem ASC, p.id ASC";
        $pautas = $this->db->fetchAll($sqlPautas, [
            ':aid'              => $assembleiaId,
            ':total_presentes'  => $totalPresentes,
            ':total_elegiveis'  => $totalElegiveis,
        ]) ?: [];

        $pautasFormatadas = [];
        foreach ($pautas as $p) {
            $sim = (int)($p['votos_sim'] ?? 0);
            $nao = (int)($p['votos_nao'] ?? 0);
            $totVotos = (int)($p['total_votos'] ?? 0);
            $presentes = (int)($p['total_presentes'] ?? 0);
            // Abstenções = presentes (que podiam votar) - efetivamente votaram
            $abst = max(0, $presentes - $totVotos);

            $denominador = max(1, $sim + $nao);
            $pctSim   = round(($sim * 100.0) / $denominador, 1);
            $pctNao   = round(($nao * 100.0) / $denominador, 1);
            $pctAbst  = $presentes > 0 ? round(($abst * 100.0) / $presentes, 1) : 0.0;

            if ($sim > $nao)       $resultado = 'Aprovada';
            elseif ($nao > $sim)   $resultado = 'Rejeitada';
            else                   $resultado = 'Empate';

            $pautasFormatadas[] = array_merge($p, [
                'votos_sim'     => $sim,
                'votos_nao'     => $nao,
                'abstencoes'    => $abst,
                'pct_sim'       => $pctSim,
                'pct_nao'       => $pctNao,
                'pct_abstencao' => $pctAbst,
                'resultado'     => $resultado,
            ]);
        }

        $pctPresenca = $totalElegiveis > 0 ? round(($totalPresentes * 100.0) / $totalElegiveis, 1) : 0.0;
        return [
            'pautas' => $pautasFormatadas,
            'quorum' => [
                'total_unidades_condominio' => $totalElegiveis,
                'total_presentes'           => $totalPresentes,
                'pct_presenca'              => $pctPresenca,
            ],
        ];
    }

    public function getVotos($pautaId)
    {
        $sql = "SELECT vp.*,
                       u.lote, u.casa,
                       dono.nome as dono_nome, dono.cpf as dono_cpf,
                       vot.nome as votante_nome, vot.cpf as votante_cpf
                FROM votos_pautas vp
                INNER JOIN unidades u ON vp.unidade_id = u.id
                LEFT  JOIN usuarios dono ON u.usuario_id = dono.id
                INNER JOIN usuarios vot ON vp.usuario_votante_id = vot.id
                WHERE vp.pauta_id = :pauta_id
                ORDER BY dono.nome ASC";
        return $this->db->fetchAll($sql, [':pauta_id' => $pautaId]);
    }

    public function atualizarResultado($id)
    {
        $sql = "SELECT
                    SUM(CASE WHEN voto = 'Sim' THEN 1 ELSE 0 END) as sim,
                    SUM(CASE WHEN voto = 'Não' THEN 1 ELSE 0 END) as nao
                FROM votos_pautas WHERE pauta_id = :id";
        $result = $this->db->fetchOne($sql, [':id' => $id]);
        $sim = (int) ($result['sim'] ?? 0);
        $nao = (int) ($result['nao'] ?? 0);
        $status = $sim > $nao ? 'Aprovada' : ($sim < $nao ? 'Rejeitada' : 'Em votação');
        return $this->db->update('pautas', ['status' => $status], 'id = :id', [':id' => $id]);
    }
}
