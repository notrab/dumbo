<?php

namespace Dumbo\Helpers;

use PDO;
use PDOException;
use Exception;

class DatabaseHelper
{
    private $pdo;

    public function __construct($host, $dbname, $user, $password)
    {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";
        try {
            $this->pdo = new PDO($dsn, $user, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new Exception('Database connection error');
        }
    }

    public function table($table)
    {
        return new QueryBuilder($this->pdo, $table);
    }
}

class QueryBuilder
{
    private $pdo;
    private $table;
    private $select = '*';
    private $where = [];
    private $params = [];

    public function __construct(PDO $pdo, $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function select($columns = '*')
    {
        $this->select = $columns;
        return $this;
    }

    public function where($column, $operator, $value)
    {
        $this->where[] = "$column $operator :$column";
        $this->params[$column] = $value;
        return $this;
    }

    public function get()
    {
        $whereClause = count($this->where) ? 'WHERE ' . implode(' AND ', $this->where) : '';
        $sql = "SELECT {$this->select} FROM {$this->table} $whereClause";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($data)
    {
        $fields = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $sql = "INSERT INTO {$this->table} ($fields) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    public function update($data)
    {
        $set = "";
        foreach ($data as $key => $value) {
            $set .= "$key = :$key, ";
        }
        $set = rtrim($set, ", ");
        $sql = "UPDATE {$this->table} SET $set";
        if ($this->where) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($data, $this->params));
        return $this;
    }

    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";
        if ($this->where) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $this;
    }
}
