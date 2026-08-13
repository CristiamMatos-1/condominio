<?php

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // ====================== SEGURANÇA (LGPD) =========================
            // NUNCA exibimos "Erro de conexão ... mensagem raw" para usuário
            // final (evita vazar host, nome de banco, versões MySQL, credenciais
            // em mensagens como "Access denied for user ...@localhost with password").
            // Fazemos log estruturado apenas no servidor e lançamos exceção
            // genérica capturada pelo front controller.
            $logPayload = json_encode([
                'evento'    => 'db_conexao_falhou',
                'classe'    => get_class($e),
                'mensagem'  => $e->getMessage(),
                'arquivo'   => $e->getFile(),
                'linha'     => $e->getLine(),
                'db_host'   => DB_HOST,
                'db_name'   => DB_NAME,
                'db_charset'=> DB_CHARSET,
                'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
                'timestamp' => date('c'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            @error_log('[SEG-DB] ' . ($logPayload ?: 'db conexao falhou'));
            throw new RuntimeException(
                'Sistema indisponível no momento (falha ao conectar no banco de dados). '
                . 'Por favor, tente novamente em alguns instantes ou contate o suporte.'
            );
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch() ?: null;
    }

    public function insert($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "{$key} = :set_{$key}";
        }
        $setClause = implode(', ', $set);

        $params = [];
        foreach ($data as $key => $value) {
            $params[':set_' . $key] = $value;
        }
        foreach ($whereParams as $key => $value) {
            $params[$key] = $value;
        }

        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $where, $whereParams = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $whereParams);
        return $stmt->rowCount();
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollBack()
    {
        return $this->pdo->rollBack();
    }
}
