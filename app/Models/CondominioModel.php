<?php

require_once __DIR__ . '/Database.php';

class CondominioModel
{
    private $db;

    /**
     * Colunas usadas em listagens (minimização LGPD): não traz colunas de
     * configuração interna ou dados sensíveis caso existam no futuro.
     */
    private const COLUNAS_GRID = 'id, nome, cnpj, endereco, cidade, estado, cep, email, telefone, ativo, created_at';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 🛡️ getAll — parametrizado, filtra por ativo.
     * Suporta também filtro por IDs ou nome se precisar no futuro.
     *
     * @param int|null $ativo Filtrar por ativo (null = todos)
     * @param int|null $condominioId Filtrar por ID (para tenant isolation quando chamado por admin_condominio)
     * @return array
     */
    public function getAll(?int $ativo = null, ?int $condominioId = null): array
    {
        $sql    = "SELECT " . self::COLUNAS_GRID . " FROM condominios WHERE 1=1";
        $params = [];
        if ($ativo !== null) {
            $sql .= " AND ativo = :ativo";
            $params[':ativo'] = $ativo;
        }
        if ($condominioId !== null) {
            $sql .= " AND id = :cid";
            $params[':cid'] = $condominioId;
        }
        $sql .= " ORDER BY nome ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT " . self::COLUNAS_GRID . " FROM condominios WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]) ?: null;
    }

    /**
     * 🛡️ create — validado e protegido contra mass assignment.
     * Aceita APENAS os campos conhecidos; valida obrigatoriedade de nome.
     */
    public function create(array $data)
    {
        $nome = trim((string)($data['nome'] ?? ''));
        if ($nome === '') {
            throw new InvalidArgumentException('Nome do condomínio é obrigatório.');
        }
        $campos = [
            'nome'     => $nome,
            'cnpj'     => !empty($data['cnpj']) ? preg_replace('/\D/', '', (string)$data['cnpj']) : null,
            'endereco' => !empty($data['endereco']) ? trim((string)$data['endereco']) : null,
            'cidade'   => !empty($data['cidade']) ? trim((string)$data['cidade']) : null,
            'estado'   => !empty($data['estado']) ? trim((string)$data['estado']) : null,
            'cep'      => !empty($data['cep']) ? preg_replace('/\D/', '', (string)$data['cep']) : null,
            'email'    => !empty($data['email']) ? trim((string)$data['email']) : null,
            'telefone' => !empty($data['telefone']) ? trim((string)$data['telefone']) : null,
            'ativo'    => isset($data['ativo']) ? (int)$data['ativo'] : 1,
        ];
        // Limpa CNPJ e CEP brutos, evita caracteres de formatação no banco.
        if ($campos['cnpj'] !== null && strlen($campos['cnpj']) !== 14) $campos['cnpj'] = null;
        if ($campos['cep']  !== null && strlen($campos['cep'])  !==  8) $campos['cep']  = null;
        return $this->db->insert('condominios', $campos);
    }

    /**
     * 🛡️ update — validado e sem mass assignment.
     * Campos iguais aos do create. Não aceita inputs extras!
     */
    public function update(int $id, array $data): int
    {
        $nome = trim((string)($data['nome'] ?? ''));
        if ($nome === '') {
            throw new InvalidArgumentException('Nome do condomínio é obrigatório.');
        }
        $campos = [
            'nome'     => $nome,
            'cnpj'     => !empty($data['cnpj']) ? preg_replace('/\D/', '', (string)$data['cnpj']) : null,
            'endereco' => !empty($data['endereco']) ? trim((string)$data['endereco']) : null,
            'cidade'   => !empty($data['cidade']) ? trim((string)$data['cidade']) : null,
            'estado'   => !empty($data['estado']) ? trim((string)$data['estado']) : null,
            'cep'      => !empty($data['cep']) ? preg_replace('/\D/', '', (string)$data['cep']) : null,
            'email'    => !empty($data['email']) ? trim((string)$data['email']) : null,
            'telefone' => !empty($data['telefone']) ? trim((string)$data['telefone']) : null,
            'ativo'    => isset($data['ativo']) ? (int)$data['ativo'] : 1,
        ];
        if ($campos['cnpj'] !== null && strlen($campos['cnpj']) !== 14) $campos['cnpj'] = null;
        if ($campos['cep']  !== null && strlen($campos['cep'])  !==  8) $campos['cep']  = null;
        return $this->db->update('condominios', $campos, 'id = :id', [':id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('condominios', 'id = :id', [':id' => $id]);
    }

    public function count(?int $ativo = null): int
    {
        $sql    = "SELECT COUNT(*) as total FROM condominios WHERE 1=1";
        $params = [];
        if ($ativo !== null) {
            $sql .= " WHERE ativo = :ativo";
            $params[':ativo'] = $ativo;
        }
        $result = $this->db->fetchOne($sql, $params);
        return (int) ($result['total'] ?? 0);
    }
}
