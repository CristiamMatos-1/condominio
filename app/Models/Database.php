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
            die('Erro de conexão com o banco de dados: ' . $e->getMessage());
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
