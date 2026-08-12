<?php

require_once __DIR__ . '/Database.php';

class AssembleiaModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll($condominioId = null, $status = null)
    {
        $sql = "SELECT a.*, c.nome as condominio_nome
                FROM assembleias a
                INNER JOIN condominios c ON a.condominio_id = c.id
                WHERE 1=1";
        $params = [];
        if ($condominioId !== null) {
            $sql .= " AND a.condominio_id = :condominio_id";
            $params[':condominio_id'] = $condominioId;
        }
        if ($status !== null) {
            $sql .= " AND a.status = :status";
            $params[':status'] = $status;
        }
        $sql .= " ORDER BY a.data_assembleia DESC, a.horario DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getById($id)
    {
        $sql = "SELECT a.*, c.nome as condominio_nome, c.endereco, c.cidade, c.estado
                FROM assembleias a
                INNER JOIN condominios c ON a.condominio_id = c.id
                WHERE a.id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    public function getAbertaPorCondominio($condominioId)
    {
        $sql = "SELECT a.*, c.nome as condominio_nome, c.endereco, c.cidade, c.estado
                FROM assembleias a
                INNER JOIN condominios c ON a.condominio_id = c.id
                WHERE a.condominio_id = :condominio_id AND a.status = 'Aberta'
                ORDER BY a.data_assembleia DESC, a.horario DESC
                LIMIT 1";
        return $this->db->fetchOne($sql, [':condominio_id' => $condominioId]);
    }

    public function getAbertaParaUsuario($usuarioId)
    {
        $sql = "SELECT DISTINCT a.*, c.nome as condominio_nome, c.endereco
                FROM assembleias a
                INNER JOIN condominios c ON a.condominio_id = c.id
                INNER JOIN unidades u ON u.condominio_id = c.id
                WHERE a.status = 'Aberta' AND u.ativo = 1 AND (
                    u.usuario_id = :usuario_id
                    OR EXISTS (SELECT 1 FROM procuracoes p
                               WHERE p.unidade_id = u.id AND p.representante_id = :usuario_id2 AND p.ativo = 1)
                )
                ORDER BY c.nome ASC, a.data_assembleia ASC";
        return $this->db->fetchAll($sql, [
            ':usuario_id'  => $usuarioId,
            ':usuario_id2' => $usuarioId,
        ]);
    }

    public function create($data)
    {
        return $this->db->insert('assembleias', [
            'condominio_id'   => $data['condominio_id'],
            'titulo'          => $data['titulo'],
            'tipo'            => $data['tipo'],
            'data_assembleia' => $data['data_assembleia'],
            'horario'         => $data['horario'],
            'status'          => isset($data['status']) ? $data['status'] : 'Fechada',
            'observacoes'     => !empty($data['observacoes']) ? $data['observacoes'] : null,
        ]);
    }

    public function update($id, $data)
    {
        return $this->db->update('assembleias', [
            'condominio_id'   => $data['condominio_id'],
            'titulo'          => $data['titulo'],
            'tipo'            => $data['tipo'],
            'data_assembleia' => $data['data_assembleia'],
            'horario'         => $data['horario'],
            'status'          => isset($data['status']) ? $data['status'] : 'Fechada',
            'observacoes'     => !empty($data['observacoes']) ? $data['observacoes'] : null,
        ], 'id = :id', [':id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete('assembleias', 'id = :id', [':id' => $id]);
    }

    public function registrarPresenca($assembleiaId, $unidadeId, $usuarioId, $viaProcuracao = 0, $procuracaoId = null)
    {
        try {
            return $this->db->insert('presencas_assembleia', [
                'assembleia_id'     => $assembleiaId,
                'unidade_id'        => $unidadeId,
                'usuario_presente_id' => $usuarioId,
                'via_procuracao'    => $viaProcuracao,
                'procuracao_id'     => $procuracaoId,
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1062 Duplicate') !== false) {
                return null;
            }
            throw $e;
        }
    }

    public function getPresencas($assembleiaId)
    {
        $sql = "SELECT pa.*,
                       u.lote, u.casa, u.condominio_id,
                       dono.nome as dono_nome, dono.cpf as dono_cpf,
                       rep.nome as usuario_presente_nome, rep.cpf as usuario_presente_cpf,
                       p.num_documento as procuracao_num
                FROM presencas_assembleia pa
                INNER JOIN unidades u ON pa.unidade_id = u.id
                INNER JOIN usuarios dono ON u.usuario_id = dono.id
                INNER JOIN usuarios rep ON pa.usuario_presente_id = rep.id
                LEFT JOIN procuracoes p ON pa.procuracao_id = p.id
                WHERE pa.assembleia_id = :assembleia_id
                ORDER BY pa.via_procuracao ASC, dono.nome ASC";
        return $this->db->fetchAll($sql, [':assembleia_id' => $assembleiaId]);
    }

    public function hasPresenca($assembleiaId, $unidadeId)
    {
        $sql = "SELECT COUNT(*) as total FROM presencas_assembleia
                WHERE assembleia_id = :assembleia_id AND unidade_id = :unidade_id";
        $result = $this->db->fetchOne($sql, [
            ':assembleia_id' => $assembleiaId,
            ':unidade_id'    => $unidadeId,
        ]);
        return (int) ($result['total'] ?? 0) > 0;
    }

    public function countPresencas($assembleiaId)
    {
        $sql = "SELECT COUNT(*) as total FROM presencas_assembleia WHERE assembleia_id = :assembleia_id";
        $result = $this->db->fetchOne($sql, [':assembleia_id' => $assembleiaId]);
        return (int) ($result['total'] ?? 0);
    }

    public function setStatus($id, $status)
    {
        return $this->db->update('assembleias', [
            'status' => $status,
        ], 'id = :id', [':id' => $id]);
    }

    /**
     * Retorna TODAS as unidades que o usuario pode usar para VOTAR
     * em uma assembleia ESPECIFICA.
     *
     * Resolve o caso onde: usuario NAO eh dono da unidade (unidades.usuario_id
     * nao aponta para ele) e NAO tem procuração, mas ADMIN deu presença MANUAL
     * na tabela presencas_assembleia (usuario_presente_id = $usuarioId).
     *
     * Uniao de 3 fontes (sem duplicatas por id de unidade):
     *   A) Unidades proprias + procurações (getTodasUnidadesUsuario)
     *   B) Unidades onde a pessoa esta PRESENTE nesta assembleia (presencas_assembleia)
     *
     * Campos retornados = MESMOS do getTodasUnidadesUsuario (100% compatível):
     *   id, condominio_id, lote, casa, ativo, usuario_id, metragem, fracao_ideal,
     *   taxa_condominio, observacoes, created_at, updated_at,
     *   condominio_nome, dono_nome, procuracao_id, num_documento, via_procuracao
     */
    public function getUnidadesHabilitadasParaVoto($usuarioId, $assembleiaId, $condominioId = null): array
    {
        // (A) Fontes clasicas: dono + procuracoes
        $procuracaoModel = new ProcuracaoModel();
        $fonteA = $procuracaoModel->getTodasUnidadesUsuario($usuarioId, $condominioId);
        if (!is_array($fonteA)) $fonteA = [];

        // (B) Fonte fallback: presencas registradas pelo ADMIN nesta assembleia
        //    (unidade pode ter usuario_id NULL ou de outra pessoa, mas a
        //     pessoa foi dada como presente manualmente = tem direito a voto).
        //
        // Obs.: unidades.usuario_id PODE ser NULL → LEFT JOIN dono para
        //       nao perder a linha (INNER JOIN excluiria).
        $sqlB = "SELECT u.*,
                        c.nome as condominio_nome,
                        COALESCE(dono.nome, '—') as dono_nome,
                        null as procuracao_id,
                        null as num_documento,
                        0 as via_procuracao
                 FROM presencas_assembleia pa
                 INNER JOIN unidades u     ON pa.unidade_id = u.id
                 INNER JOIN condominios c  ON u.condominio_id = c.id
                 LEFT  JOIN usuarios dono  ON u.usuario_id = dono.id
                 WHERE pa.assembleia_id = :assembleia_id
                   AND pa.usuario_presente_id = :usuario_id
                   AND u.ativo = 1";
        $paramsB = [
            ':assembleia_id' => (int)$assembleiaId,
            ':usuario_id'    => (int)$usuarioId,
        ];
        if ($condominioId !== null) {
            $sqlB .= " AND u.condominio_id = :condominio_id";
            $paramsB[':condominio_id'] = (int)$condominioId;
        }
        $sqlB .= " ORDER BY c.nome ASC, u.lote ASC, u.casa ASC";
        $fonteB = $this->db->fetchAll($sqlB, $paramsB);
        if (!is_array($fonteB)) $fonteB = [];

        // Merge e deduplicação por unidade.id
        $vistas = [];
        $resultado = [];
        foreach ([$fonteA, $fonteB] as $fonte) {
            foreach ($fonte as $un) {
                $id = isset($un['id']) ? (int)$un['id'] : 0;
                if ($id <= 0) continue;
                if (isset($vistas[$id])) continue; // jah veio da fonte A (melhor dado dono/procuração)
                $vistas[$id] = true;
                $resultado[] = $un;
            }
        }
        // Ordenação final (fonteA jah estava ordenado; mas merge pode quebrar)
        usort($resultado, static function ($a, $b) {
            $cmp = strcasecmp((string)($a['condominio_nome'] ?? ''), (string)($b['condominio_nome'] ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = strnatcasecmp((string)($a['lote'] ?? ''), (string)($b['lote'] ?? ''));
            if ($cmp !== 0) return $cmp;
            return strnatcasecmp((string)($a['casa'] ?? ''), (string)($b['casa'] ?? ''));
        });
        return $resultado;
    }
}
