<?php

require_once __DIR__ . '/Database.php';

class UsuarioModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll($tipo = null, $ativo = null)
    {
        $sql = "SELECT * FROM usuarios WHERE 1=1";
        $params = [];
        if ($tipo !== null) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }
        if ($ativo !== null) {
            $sql .= " AND ativo = :ativo";
            $params[':ativo'] = $ativo;
        }
        $sql .= " ORDER BY nome ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM usuarios WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    public function getByCpf($cpf)
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        $cpf = formatCpf($cpf);
        $sql = "SELECT * FROM usuarios WHERE cpf = :cpf";
        return $this->db->fetchOne($sql, [':cpf' => $cpf]);
    }

    public function getByCpfRaw($cpf)
    {
        $sql = "SELECT * FROM usuarios WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = :cpf";
        return $this->db->fetchOne($sql, [':cpf' => $cpf]);
    }

    public function create($data)
    {
        $cpf = preg_replace('/\D/', '', $data['cpf']);
        $cpfFormatado = formatCpf($cpf);

        return $this->db->insert('usuarios', [
            'nome'     => $data['nome'],
            'cpf'      => $cpfFormatado,
            'email'    => !empty($data['email']) ? $data['email'] : null,
            'telefone' => !empty($data['telefone']) ? $data['telefone'] : null,
            'tipo'     => isset($data['tipo']) ? $data['tipo'] : 'morador',
            'senha'    => !empty($data['senha']) ? password_hash($data['senha'], PASSWORD_DEFAULT) : null,
            'ativo'    => isset($data['ativo']) ? $data['ativo'] : 1,
        ]);
    }

    public function update($id, $data)
    {
        $updateData = [
            'nome'     => $data['nome'],
            'email'    => !empty($data['email']) ? $data['email'] : null,
            'telefone' => !empty($data['telefone']) ? $data['telefone'] : null,
            'tipo'     => isset($data['tipo']) ? $data['tipo'] : 'morador',
            'ativo'    => isset($data['ativo']) ? $data['ativo'] : 1,
        ];

        if (!empty($data['cpf'])) {
            $cpf = preg_replace('/\D/', '', $data['cpf']);
            $updateData['cpf'] = formatCpf($cpf);
        }

        if (!empty($data['senha'])) {
            $updateData['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }

        return $this->db->update('usuarios', $updateData, 'id = :id', [':id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete('usuarios', 'id = :id', [':id' => $id]);
    }

    public function verificaSenha($usuarioId, $senha)
    {
        $usuario = $this->getById($usuarioId);
        if (!$usuario || empty($usuario['senha'])) {
            return false;
        }
        return password_verify($senha, $usuario['senha']);
    }
}
