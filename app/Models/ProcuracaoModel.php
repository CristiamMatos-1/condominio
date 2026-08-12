<?php

require_once __DIR__ . '/Database.php';

class ProcuracaoModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll($condominioId = null, $ativo = null)
    {
        $sql = "SELECT p.*,
                       u.lote, u.casa,
                       dono.nome as dono_nome, dono.cpf as dono_cpf,
                       rep.nome as representante_nome, rep.cpf as representante_cpf,
                       c.nome as condominio_nome
                FROM procuracoes p
                INNER JOIN unidades u ON p.unidade_id = u.id
                INNER JOIN usuarios dono ON u.usuario_id = dono.id
                INNER JOIN usuarios rep ON p.representante_id = rep.id
                INNER JOIN condominios c ON u.condominio_id = c.id
                WHERE 1=1";
        $params = [];
        if ($condominioId !== null) {
            $sql .= " AND u.condominio_id = :condominio_id";
            $params[':condominio_id'] = $condominioId;
        }
        if ($ativo !== null) {
            $sql .= " AND p.ativo = :ativo";
            $params[':ativo'] = $ativo;
        }
        $sql .= " ORDER BY c.nome ASC, rep.nome ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById($id)
    {
        $sql = "SELECT p.*,
                       u.lote, u.casa, u.condominio_id,
                       dono.nome as dono_nome, dono.cpf as dono_cpf,
                       rep.nome as representante_nome, rep.cpf as representante_cpf
                FROM procuracoes p
                INNER JOIN unidades u ON p.unidade_id = u.id
                INNER JOIN usuarios dono ON u.usuario_id = dono.id
                INNER JOIN usuarios rep ON p.representante_id = rep.id
                WHERE p.id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    public function getByRepresentante($representanteId, $condominioId = null)
    {
        $sql = "SELECT p.*,
                       u.lote, u.casa, u.condominio_id,
                       dono.nome as dono_nome, dono.cpf as dono_cpf,
                       c.nome as condominio_nome
                FROM procuracoes p
                INNER JOIN unidades u ON p.unidade_id = u.id
                INNER JOIN usuarios dono ON u.usuario_id = dono.id
                INNER JOIN condominios c ON u.condominio_id = c.id
                WHERE p.representante_id = :representante_id AND p.ativo = 1 AND u.ativo = 1";
        $params = [':representante_id' => $representanteId];
        if ($condominioId !== null) {
            $sql .= " AND u.condominio_id = :condominio_id";
            $params[':condominio_id'] = $condominioId;
        }
        $sql .= " ORDER BY c.nome ASC, u.lote ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getUnidadesQueUsuarioRepresenta($usuarioId, $condominioId = null)
    {
        $sql = "SELECT DISTINCT u.*, c.nome as condominio_nome,
                       dono.nome as dono_nome, p.id as procuracao_id, p.num_documento,
                       1 as via_procuracao
                FROM procuracoes p
                INNER JOIN unidades u ON p.unidade_id = u.id
                INNER JOIN condominios c ON u.condominio_id = c.id
                INNER JOIN usuarios dono ON u.usuario_id = dono.id
                WHERE p.representante_id = :usuario_id AND p.ativo = 1 AND u.ativo = 1";
        $params = [':usuario_id' => $usuarioId];
        if ($condominioId !== null) {
            $sql .= " AND u.condominio_id = :condominio_id";
            $params[':condominio_id'] = $condominioId;
        }
        return $this->db->fetchAll($sql, $params);
    }

    public function getTodasUnidadesUsuario($usuarioId, $condominioId = null)
    {
        $sql = "SELECT u.*, c.nome as condominio_nome,
                       usr.nome as dono_nome, null as procuracao_id, null as num_documento,
                       0 as via_procuracao
                FROM unidades u
                INNER JOIN condominios c ON u.condominio_id = c.id
                INNER JOIN usuarios usr ON u.usuario_id = usr.id
                WHERE u.usuario_id = :usuario_id AND u.ativo = 1";
        $params = [':usuario_id' => $usuarioId];
        if ($condominioId !== null) {
            $sql .= " AND u.condominio_id = :condominio_id";
            $params[':condominio_id'] = $condominioId;
        }

        $sql .= " UNION ALL ";

        $sql .= "SELECT u.*, c.nome as condominio_nome,
                       dono.nome as dono_nome, p.id as procuracao_id, p.num_documento,
                       1 as via_procuracao
                FROM procuracoes p
                INNER JOIN unidades u ON p.unidade_id = u.id
                INNER JOIN condominios c ON u.condominio_id = c.id
                INNER JOIN usuarios dono ON u.usuario_id = dono.id
                WHERE p.representante_id = :usuario_id2 AND p.ativo = 1 AND u.ativo = 1";
        $params[':usuario_id2'] = $usuarioId;
        if ($condominioId !== null) {
            $sql .= " AND u.condominio_id = :condominio_id2";
            $params[':condominio_id2'] = $condominioId;
        }
        $sql .= " ORDER BY condominio_nome ASC, lote ASC, casa ASC";

        return $this->db->fetchAll($sql, $params);
    }

    public function create($data)
    {
        return $this->db->insert('procuracoes', [
            'unidade_id'       => $data['unidade_id'],
            'representante_id' => $data['representante_id'],
            'num_documento'    => $data['num_documento'],
            'data_outorgacao'  => !empty($data['data_outorgacao']) ? $data['data_outorgacao'] : null,
            'ativo'            => isset($data['ativo']) ? $data['ativo'] : 1,
        ]);
    }

    public function update($id, $data)
    {
        return $this->db->update('procuracoes', [
            'unidade_id'       => $data['unidade_id'],
            'representante_id' => $data['representante_id'],
            'num_documento'    => $data['num_documento'],
            'data_outorgacao'  => !empty($data['data_outorgacao']) ? $data['data_outorgacao'] : null,
            'ativo'            => isset($data['ativo']) ? $data['ativo'] : 1,
        ], 'id = :id', [':id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete('procuracoes', 'id = :id', [':id' => $id]);
    }

    public function exists($unidadeId, $representanteId, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) as total FROM procuracoes
                WHERE unidade_id = :unidade_id AND representante_id = :representante_id AND ativo = 1";
        $params = [
            ':unidade_id'       => $unidadeId,
            ':representante_id' => $representanteId,
        ];
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $result = $this->db->fetchOne($sql, $params);
        return (int) ($result['total'] ?? 0) > 0;
    }
}
