<?php

require_once __DIR__ . '/Database.php';

class UsuarioModel
{
    private $db;

    /**
     * Colunas "PÚBLICAS" (LGPD): usadas em grids e listagens.
     * NUNCA incluem CPF (dado sensível, art. 5º LGPD) nem hash de senha.
     */
    private const COLUNAS_GRID = 'id, nome, email, telefone, tipo, perfil, condominio_id, ativo, created_at';

    /**
     * Colunas "PRIVADAS": usadas APENAS no caminho de login/autenticação.
     * Incluem CPF para validar credencial e hash de senha para verificar.
     */
    private const COLUNAS_LOGIN = 'id, nome, cpf, email, telefone, tipo, perfil, condominio_id, ativo, senha, created_at';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 🛡️ getAll — versão "GRID / PÚBLICA" (LGPD - Minimização de Dados).
     * NÃO retorna CPF nem hash de senha. Ideal para listagens e vistas.
     *
     * @param string|null $tipo   Filtrar por tipo legado (ex.: 'morador')
     * @param int|null    $ativo  Filtrar por status ativo (1 = ativo, 0 = inativo)
     * @param string|null $perfil Filtrar por perfil RBAC (ex.: 'admin_condominio'/'super_admin'/'morador')
     * @param int|null    $condominioId Filtrar por condomínio (tenant isolation)
     */
    public function getAll(?string $tipo = null, ?int $ativo = null, ?string $perfil = null, ?int $condominioId = null): array
    {
        $sql    = "SELECT " . self::COLUNAS_GRID . " FROM usuarios WHERE 1=1";
        $params = [];
        if ($tipo !== null && $tipo !== '') {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }
        if ($ativo !== null) {
            $sql .= " AND ativo = :ativo";
            $params[':ativo'] = $ativo;
        }
        if ($perfil !== null && $perfil !== '') {
            $sql .= " AND perfil = :perfil";
            $params[':perfil'] = $perfil;
        }
        if ($condominioId !== null) {
            $sql .= " AND condominio_id = :condominio_id";
            $params[':condominio_id'] = $condominioId;
        }
        $sql .= " ORDER BY nome ASC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 🛡️ getById — versão PÚBLICA (não retorna CPF nem senha).
     * Use em telas de edição / visualização.
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT " . self::COLUNAS_GRID . " FROM usuarios WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]) ?: null;
    }

    // =============================== LOGIN =================================
    // 🚨 MÉTODOS RESTRINGIDOS AO CAMINHO DE AUTENTICAÇÃO.
    // NÃO usar em views. Retornam CPF e hash da senha.
    // ======================================================================

    /**
     * 🔐 Uso INTERNO apenas em AuthController/login — retorna CPF e hash.
     */
    public function getByCpf(string $cpf): ?array
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        $cpfFormatado = function_exists('formatCpf') ? formatCpf($cpf) : $this->formatCpfInterno($cpf);
        $sql = "SELECT " . self::COLUNAS_LOGIN . " FROM usuarios WHERE cpf = :cpf";
        return $this->db->fetchOne($sql, [':cpf' => $cpfFormatado]) ?: null;
    }

    /**
     * 🔐 Uso INTERNO em AuthController/login — por CPF raw, tolera máscaras.
     */
    public function getByCpfRaw(string $cpf): ?array
    {
        $sql = "SELECT " . self::COLUNAS_LOGIN . " FROM usuarios
                WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = :cpf";
        return $this->db->fetchOne($sql, [':cpf' => $cpf]) ?: null;
    }

    /**
     * 🔐 Uso INTERNO apenas para verificação de senha após login.
     */
    public function getByIdParaLogin(int $id): ?array
    {
        $sql = "SELECT " . self::COLUNAS_LOGIN . " FROM usuarios WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]) ?: null;
    }

    // =============================== CRUD ==================================

    /**
     * 🛡️ create — validado e protegido contra mass assignment.
     * Aceita APENAS as colunas conhecidas. Suporta colunas novas (perfil,
     * condominio_id) introduzidas pela migration 003_multi_tenant_rbac.
     *
     * @param array $data Campos: nome, cpf [, email, telefone, tipo, perfil,
     *                    condominio_id, senha, ativo]
     * @return int|false ID inserido ou false em caso de erro tratado.
     * @throws PDOException Repassa Duplicate Entry (CPF duplicado) para
     *                      controller gerir mensagem amigável e log seguro.
     */
    public function create(array $data)
    {
        $cpf = preg_replace('/\D/', '', (string)($data['cpf'] ?? ''));
        if (strlen($cpf) !== 11) {
            throw new InvalidArgumentException('CPF deve conter 11 dígitos numéricos.');
        }
        $cpfFormatado = function_exists('formatCpf') ? formatCpf($cpf) : $this->formatCpfInterno($cpf);

        $campos = [
            'nome'          => trim((string)($data['nome'] ?? '')),
            'cpf'           => $cpfFormatado,
            'email'         => !empty($data['email']) ? trim((string)$data['email']) : null,
            'telefone'      => !empty($data['telefone']) ? trim((string)$data['telefone']) : null,
            'tipo'          => !empty($data['tipo']) ? (string)$data['tipo'] : 'morador',
            'senha'         => !empty($data['senha']) ? password_hash((string)$data['senha'], PASSWORD_DEFAULT) : null,
            'ativo'         => isset($data['ativo']) ? (int)$data['ativo'] : 1,
        ];

        // Campos das migrations recentes (só incluímos se existir em $data,
        // evita erro SQL se coluna não existir em bancos antigos).
        if (array_key_exists('perfil', $data)) {
            $campos['perfil'] = (string)$data['perfil'];
        }
        if (array_key_exists('condominio_id', $data)) {
            $campos['condominio_id'] = $data['condominio_id'] !== null && $data['condominio_id'] !== ''
                ? (int)$data['condominio_id']
                : null;
        }

        return $this->db->insert('usuarios', $campos);
    }

    /**
     * 🛡️ update — versão SEGURA (campo-a-campo; NÃO aceita passagem bruta $_POST).
     * IMPORTANTE LGPD: se o CPF NÃO foi enviado no payload (tela edição input vazio),
     * NÃO alteramos o CPF persistido. Evita "acidentalmente limpar CPF ao salvar".
     *
     * @param int   $id   ID do usuário
     * @param array $data Campos aceitos: nome, email, telefone, tipo, perfil,
     *                    condominio_id, ativo. Senha e CPF só alteram se presentes.
     */
    public function update(int $id, array $data): int
    {
        $updateData = [
            'nome'          => trim((string)($data['nome'] ?? '')),
            'email'         => !empty($data['email']) ? trim((string)$data['email']) : null,
            'telefone'      => !empty($data['telefone']) ? trim((string)$data['telefone']) : null,
            'tipo'          => !empty($data['tipo']) ? (string)$data['tipo'] : 'morador',
            'ativo'         => isset($data['ativo']) ? (int)$data['ativo'] : 1,
        ];
        if (array_key_exists('perfil', $data) && $data['perfil'] !== null) {
            $updateData['perfil'] = (string)$data['perfil'];
        }
        if (array_key_exists('condominio_id', $data)) {
            $updateData['condominio_id'] = $data['condominio_id'] !== null && $data['condominio_id'] !== ''
                ? (int)$data['condominio_id']
                : null;
        }
        if (!empty($data['cpf'])) {
            $cpf = preg_replace('/\D/', '', (string)$data['cpf']);
            if (strlen($cpf) === 11) {
                $updateData['cpf'] = function_exists('formatCpf') ? formatCpf($cpf) : $this->formatCpfInterno($cpf);
            }
        }
        if (!empty($data['senha'])) {
            $updateData['senha'] = password_hash((string)$data['senha'], PASSWORD_DEFAULT);
        }

        return $this->db->update('usuarios', $updateData, 'id = :id', [':id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('usuarios', 'id = :id', [':id' => $id]);
    }

    /**
     * 🔐 Verifica senha — busca a versão PRIVADA do usuário (com hash) e compara.
     * Usa internamente getByIdParaLogin; nunca expõe a hash.
     */
    public function verificaSenha(int $usuarioId, string $senha): bool
    {
        $usuario = $this->getByIdParaLogin($usuarioId);
        if (!$usuario || empty($usuario['senha'])) return false;
        return password_verify($senha, $usuario['senha']);
    }

    /**
     * Fallback interno de formatação de CPF caso o helper global
     * não exista em bancos antigos (sem config.php atualizado).
     */
    private function formatCpfInterno(string $raw): string
    {
        $v = preg_replace('/\D/', '', $raw);
        if (strlen($v) !== 11) return $v;
        return substr($v,0,3).'.'.substr($v,3,3).'.'.substr($v,6,3).'-'.substr($v,9,2);
    }

    /**
     * Utility: verifica se CPF já existe (usado por Super Admin antes de cadastrar).
     */
    public function existsByCpf(string $cpfRaw, ?int $ignoreId = null): bool
    {
        $sql = "SELECT COUNT(*) AS qtd FROM usuarios
                WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = :cpf";
        $params = [':cpf' => preg_replace('/\D/', '', $cpfRaw)];
        if ($ignoreId !== null) {
            $sql .= " AND id != :ign";
            $params[':ign'] = $ignoreId;
        }
        $r = $this->db->fetchOne($sql, $params);
        return (int)($r['qtd'] ?? 0) > 0;
    }
}
