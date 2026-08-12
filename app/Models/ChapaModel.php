<?php

require_once __DIR__ . '/Database.php';

class ChapaModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll($assembleiaId = null)
    {
        $sql = "SELECT ch.*,
                       (SELECT COUNT(*) FROM votos_chapas vc WHERE vc.chapa_id = ch.id) as total_votos
                FROM chapas ch
                WHERE 1=1";
        $params = [];
        if ($assembleiaId !== null) {
            $sql .= " AND ch.assembleia_id = :assembleia_id";
            $params[':assembleia_id'] = $assembleiaId;
        }
        $sql .= " ORDER BY ch.ordem ASC, ch.id ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById($id)
    {
        $sql = "SELECT ch.*, a.titulo as assembleia_titulo, a.status as assembleia_status,
                       (SELECT COUNT(*) FROM votos_chapas vc WHERE vc.chapa_id = ch.id) as total_votos
                FROM chapas ch
                INNER JOIN assembleias a ON ch.assembleia_id = a.id
                WHERE ch.id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    public function create($data)
    {
        return $this->db->insert('chapas', [
            'assembleia_id' => $data['assembleia_id'],
            'nome_chapa'    => $data['nome_chapa'],
            'integrantes'   => !empty($data['integrantes']) ? $data['integrantes'] : null,
            'ordem'         => isset($data['ordem']) ? $data['ordem'] : 1,
        ]);
    }

    public function update($id, $data)
    {
        return $this->db->update('chapas', [
            'assembleia_id' => $data['assembleia_id'],
            'nome_chapa'    => $data['nome_chapa'],
            'integrantes'   => !empty($data['integrantes']) ? $data['integrantes'] : null,
            'ordem'         => isset($data['ordem']) ? $data['ordem'] : 1,
        ], 'id = :id', [':id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete('chapas', 'id = :id', [':id' => $id]);
    }

    public function votar($chapaId, $unidadeId, $usuarioId, $viaProcuracao = 0, $procuracaoId = null)
    {
        try {
            return $this->db->insert('votos_chapas', [
                'chapa_id'           => $chapaId,
                'unidade_id'         => $unidadeId,
                'usuario_votante_id' => $usuarioId,
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

    public function hasVotoNaAssembleia($assembleiaId, $unidadeId)
    {
        $sql = "SELECT COUNT(*) as total FROM votos_chapas vc
                INNER JOIN chapas ch ON vc.chapa_id = ch.id
                WHERE ch.assembleia_id = :assembleia_id AND vc.unidade_id = :unidade_id";
        $result = $this->db->fetchOne($sql, [
            ':assembleia_id' => $assembleiaId,
            ':unidade_id'    => $unidadeId,
        ]);
        return (int) ($result['total'] ?? 0) > 0;
    }

    public function getVotos($chapaId)
    {
        $sql = "SELECT vc.*,
                       u.lote, u.casa,
                       dono.nome as dono_nome, dono.cpf as dono_cpf,
                       vot.nome as votante_nome, vot.cpf as votante_cpf
                FROM votos_chapas vc
                INNER JOIN unidades u ON vc.unidade_id = u.id
                INNER JOIN usuarios dono ON u.usuario_id = dono.id
                INNER JOIN usuarios vot ON vc.usuario_votante_id = vot.id
                WHERE vc.chapa_id = :chapa_id
                ORDER BY dono.nome ASC";
        return $this->db->fetchAll($sql, [':chapa_id' => $chapaId]);
    }

    public function getResultadoAssembleia($assembleiaId)
    {
        $sql = "SELECT ch.id, ch.nome_chapa, ch.integrantes,
                       (SELECT COUNT(*) FROM votos_chapas vc WHERE vc.chapa_id = ch.id) as total_votos,
                       (SELECT COUNT(DISTINCT vc.unidade_id) FROM votos_chapas vc
                        INNER JOIN chapas ch2 ON vc.chapa_id = ch2.id
                        WHERE ch2.assembleia_id = :assembleia_id) as total_urnas
                FROM chapas ch
                WHERE ch.assembleia_id = :assembleia_id2
                ORDER BY total_votos DESC, ch.ordem ASC";
        return $this->db->fetchAll($sql, [
            ':assembleia_id'  => $assembleiaId,
            ':assembleia_id2' => $assembleiaId,
        ]);
    }
}
