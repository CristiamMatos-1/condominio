<?php

require_once __DIR__ . '/Database.php';

class CondominioModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll($ativo = null)
    {
        $sql = "SELECT * FROM condominios";
        $params = [];
        if ($ativo !== null) {
            $sql .= " WHERE ativo = :ativo";
            $params[':ativo'] = $ativo;
        }
        $sql .= " ORDER BY nome ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM condominios WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    public function create($data)
    {
        return $this->db->insert('condominios', [
            'nome'     => $data['nome'],
            'cnpj'     => !empty($data['cnpj']) ? $data['cnpj'] : null,
            'endereco' => !empty($data['endereco']) ? $data['endereco'] : null,
            'cidade'   => !empty($data['cidade']) ? $data['cidade'] : null,
            'estado'   => !empty($data['estado']) ? $data['estado'] : null,
            'cep'      => !empty($data['cep']) ? $data['cep'] : null,
            'ativo'    => isset($data['ativo']) ? $data['ativo'] : 1,
        ]);
    }

    public function update($id, $data)
    {
        return $this->db->update('condominios', [
            'nome'     => $data['nome'],
            'cnpj'     => !empty($data['cnpj']) ? $data['cnpj'] : null,
            'endereco' => !empty($data['endereco']) ? $data['endereco'] : null,
            'cidade'   => !empty($data['cidade']) ? $data['cidade'] : null,
            'estado'   => !empty($data['estado']) ? $data['estado'] : null,
            'cep'      => !empty($data['cep']) ? $data['cep'] : null,
            'ativo'    => isset($data['ativo']) ? $data['ativo'] : 1,
        ], 'id = :id', [':id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete('condominios', 'id = :id', [':id' => $id]);
    }

    public function count($ativo = null)
    {
        $sql = "SELECT COUNT(*) as total FROM condominios";
        $params = [];
        if ($ativo !== null) {
            $sql .= " WHERE ativo = :ativo";
            $params[':ativo'] = $ativo;
        }
        $result = $this->db->fetchOne($sql, $params);
        return (int) ($result['total'] ?? 0);
    }
}
