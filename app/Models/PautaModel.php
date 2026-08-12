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
        $sql = "SELECT COUNT(*) as total FROM votos_pautas
                WHERE pauta_id = :pauta_id AND unidade_id = :unidade_id";
        $result = $this->db->fetchOne($sql, [
            ':pauta_id'   => $pautaId,
            ':unidade_id' => $unidadeId,
        ]);
        return (int) ($result['total'] ?? 0) > 0;
    }

    public function getVotos($pautaId)
    {
        $sql = "SELECT vp.*,
                       u.lote, u.casa,
                       dono.nome as dono_nome, dono.cpf as dono_cpf,
                       vot.nome as votante_nome, vot.cpf as votante_cpf
                FROM votos_pautas vp
                INNER JOIN unidades u ON vp.unidade_id = u.id
                INNER JOIN usuarios dono ON u.usuario_id = dono.id
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
