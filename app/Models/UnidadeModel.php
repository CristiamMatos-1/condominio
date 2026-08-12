<?php

require_once __DIR__ . '/Database.php';

class UnidadeModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll($condominioId = null, $ativo = null)
    {
        $sql = "SELECT u.*, usr.nome as dono_nome, usr.cpf as dono_cpf, c.nome as condominio_nome
                FROM unidades u
                INNER JOIN usuarios usr ON u.usuario_id = usr.id
                INNER JOIN condominios c ON u.condominio_id = c.id
                WHERE 1=1";
        $params = [];
        if ($condominioId !== null) {
            $sql .= " AND u.condominio_id = :condominio_id";
            $params[':condominio_id'] = $condominioId;
        }
        if ($ativo !== null) {
            $sql .= " AND u.ativo = :ativo";
            $params[':ativo'] = $ativo;
        }
        $sql .= " ORDER BY c.nome ASC, u.lote ASC, u.casa ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById($id)
    {
        $sql = "SELECT u.*, usr.nome as dono_nome, usr.cpf as dono_cpf, c.nome as condominio_nome
                FROM unidades u
                INNER JOIN usuarios usr ON u.usuario_id = usr.id
                INNER JOIN condominios c ON u.condominio_id = c.id
                WHERE u.id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    public function getByUsuario($usuarioId)
    {
        $sql = "SELECT u.*, usr.nome as dono_nome, usr.cpf as dono_cpf, c.nome as condominio_nome
                FROM unidades u
                INNER JOIN usuarios usr ON u.usuario_id = usr.id
                INNER JOIN condominios c ON u.condominio_id = c.id
                WHERE u.usuario_id = :usuario_id AND u.ativo = 1
                ORDER BY c.nome ASC, u.lote ASC";
        return $this->db->fetchAll($sql, [':usuario_id' => $usuarioId]);
    }

    public function getCondominiosDoUsuario($usuarioId)
    {
        $sql = "SELECT DISTINCT c.id, c.nome, c.endereco
                FROM unidades u
                INNER JOIN condominios c ON u.condominio_id = c.id
                WHERE u.usuario_id = :usuario_id AND u.ativo = 1 AND c.ativo = 1";
        return $this->db->fetchAll($sql, [':usuario_id' => $usuarioId]);
    }

    public function create($data)
    {
        return $this->db->insert('unidades', [
            'condominio_id' => $data['condominio_id'],
            'usuario_id'    => $data['usuario_id'],
            'lote'          => $data['lote'],
            'casa'          => $data['casa'],
            'ativo'         => isset($data['ativo']) ? $data['ativo'] : 1,
        ]);
    }

    public function update($id, $data)
    {
        return $this->db->update('unidades', [
            'condominio_id' => $data['condominio_id'],
            'usuario_id'    => $data['usuario_id'],
            'lote'          => $data['lote'],
            'casa'          => $data['casa'],
            'ativo'         => isset($data['ativo']) ? $data['ativo'] : 1,
        ], 'id = :id', [':id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete('unidades', 'id = :id', [':id' => $id]);
    }

    public function count($condominioId = null)
    {
        $sql = "SELECT COUNT(*) as total FROM unidades WHERE ativo = 1";
        $params = [];
        if ($condominioId !== null) {
            $sql .= " AND condominio_id = :condominio_id";
            $params[':condominio_id'] = $condominioId;
        }
        $result = $this->db->fetchOne($sql, $params);
        return (int) ($result['total'] ?? 0);
    }
}
