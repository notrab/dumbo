<?php

class Database
{
    private $dbPath;
    private $schema;
    private $indexes = [];
    private $cache = [];
    private $cacheTTL = 300;

    public function __construct($dbPath, $schema, $cacheTTL = 300)
    {
        $this->dbPath = rtrim($dbPath, "/");
        $this->schema = $schema;
        $this->cacheTTL = $cacheTTL;

        foreach ($schema as $tableName => $fields) {
            $tableDir = $this->dbPath . "/$tableName";
            if (!is_dir($tableDir)) {
                mkdir($tableDir, 0777, true);
            }

            $this->loadIndex($tableName);
        }
    }

    public function insert($tableName, $data)
    {
        if (!isset($this->schema[$tableName])) {
            throw new Exception(
                "Table '$tableName' does not exist in the schema."
            );
        }

        $id = uniqid();
        $filename = $this->dbPath . "/$tableName/$id.txt";

        $content = ["id" => $id];
        foreach ($this->schema[$tableName] as $field => $type) {
            if (!isset($data[$field])) {
                throw new Exception(
                    "Field '$field' is required but not provided."
                );
            }
            $content[$field] = $this->formatField($data[$field], $type);

            $this->indexes[$tableName][$field][$content[$field]] = $id;
        }

        file_put_contents($filename, json_encode($content));
        $this->saveIndex($tableName);
        $this->clearCache($tableName);
        $this->clearCache($tableName . "_count");

        return $id;
    }

    public function select($tableName, $conditions = [])
    {
        $cacheKey = $this->getCacheKey($tableName, $conditions);
        $cachedResult = $this->getCache($cacheKey);

        if ($cachedResult !== null) {
            return $cachedResult;
        }

        if (!isset($this->schema[$tableName])) {
            throw new Exception(
                "Table '$tableName' does not exist in the schema."
            );
        }

        $results = [];
        if (isset($conditions["id"])) {
            $filename = $this->dbPath . "/$tableName/{$conditions["id"]}.txt";
            if (file_exists($filename)) {
                $content = json_decode(file_get_contents($filename), true);
                if ($this->matchesAllConditions($content, $conditions)) {
                    $results[] = $content;
                }
            }
        } else {
            $files = glob($this->dbPath . "/$tableName/*.txt");
            foreach ($files as $file) {
                $content = json_decode(file_get_contents($file), true);
                if ($this->matchesAllConditions($content, $conditions)) {
                    $results[] = $content;
                }
            }
        }

        $this->setCache($cacheKey, $results);

        return $results;
    }

    public function update($tableName, $id, $data)
    {
        $filename = $this->dbPath . "/$tableName/$id.txt";

        if (!file_exists($filename)) {
            throw new Exception("Record not found.");
        }

        $content = json_decode(file_get_contents($filename), true);

        foreach ($this->schema[$tableName] as $field => $type) {
            if (isset($data[$field])) {
                if (
                    isset($this->indexes[$tableName][$field][$content[$field]])
                ) {
                    unset($this->indexes[$tableName][$field][$content[$field]]);
                }
                $content[$field] = $this->formatField($data[$field], $type);
                $this->indexes[$tableName][$field][$content[$field]] = $id;
            }
        }

        file_put_contents($filename, json_encode($content));
        $this->saveIndex($tableName);
        $this->clearCache($tableName);
        $this->clearCache($tableName . "_count");
    }

    public function delete($tableName, $id)
    {
        $filename = $this->dbPath . "/$tableName/$id.txt";

        if (file_exists($filename)) {
            $content = json_decode(file_get_contents($filename), true);
            unlink($filename);

            foreach ($this->schema[$tableName] as $field => $type) {
                if (
                    isset($this->indexes[$tableName][$field][$content[$field]])
                ) {
                    unset($this->indexes[$tableName][$field][$content[$field]]);
                }
            }
            $this->saveIndex($tableName);
            $this->clearCache($tableName);
            $this->clearCache($tableName . "_count");

            return true;
        }

        return false;
    }

    public function count($tableName, $conditions = [])
    {
        $cacheKey = $this->getCacheKey($tableName . "_count", $conditions);
        $cachedResult = $this->getCache($cacheKey);

        if ($cachedResult !== null) {
            return $cachedResult;
        }

        if (!isset($this->schema[$tableName])) {
            throw new Exception(
                "Table '$tableName' does not exist in the schema."
            );
        }

        if (empty($conditions)) {
            $count = count(glob($this->dbPath . "/$tableName/*.txt"));
        } else {
            $count = 0;
            $files = glob($this->dbPath . "/$tableName/*.txt");
            foreach ($files as $file) {
                $content = json_decode(file_get_contents($file), true);
                if ($this->matchesAllConditions($content, $conditions)) {
                    $count++;
                }
            }
        }

        $this->setCache($cacheKey, $count);
        return $count;
    }

    // yolo

    private function formatField($value, $type)
    {
        switch ($type) {
            case "int":
                return (int) $value;
            case "float":
                return (float) $value;
            case "bool":
                return (bool) $value;
            case "datetime":
                return date("Y-m-d H:i:s", strtotime($value));
            default:
                return (string) $value;
        }
    }

    private function parseField($value, $type)
    {
        switch ($type) {
            case "int":
                return (int) $value;
            case "float":
                return (float) $value;
            case "bool":
                return $value === "1";
            case "datetime":
                return new DateTime($value);
            default:
                return $value;
        }
    }

    private function matchesAllConditions($record, $conditions)
    {
        foreach ($conditions as $field => $value) {
            if (!isset($record[$field]) || $record[$field] != $value) {
                return false;
            }
        }

        return true;
    }

    private function loadIndex($tableName)
    {
        $indexFile = $this->dbPath . "/$tableName/index.json";
        if (file_exists($indexFile)) {
            $this->indexes[$tableName] = json_decode(
                file_get_contents($indexFile),
                true
            );
        } else {
            $this->indexes[$tableName] = [];
            $this->rebuildIndex($tableName);
        }
    }

    private function rebuildIndex($tableName)
    {
        $files = glob($this->dbPath . "/$tableName/*.txt");

        foreach ($files as $file) {
            $id = basename($file, ".txt");
            $content = json_decode(file_get_contents($file), true);

            foreach ($this->schema[$tableName] as $field => $type) {
                if (!isset($this->indexes[$tableName][$field])) {
                    $this->indexes[$tableName][$field] = [];
                }
                $this->indexes[$tableName][$field][$content[$field]] = $id;
            }
        }

        $this->saveIndex($tableName);
    }

    private function saveIndex($tableName)
    {
        $indexFile = $this->dbPath . "/$tableName/index.json";
        file_put_contents($indexFile, json_encode($this->indexes[$tableName]));
    }

    private function getRecordIds($tableName, $conditions)
    {
        if (empty($conditions)) {
            return array_unique(
                array_merge(...array_values($this->indexes[$tableName]))
            );
        }

        $candidateIds = null;
        foreach ($conditions as $field => $value) {
            if (isset($this->indexes[$tableName][$field][$value])) {
                $ids = [$this->indexes[$tableName][$field][$value]];
                $candidateIds =
                    $candidateIds === null
                        ? $ids
                        : array_intersect($candidateIds, $ids);
            } else {
                $candidateIds = [];
                break;
            }
        }

        return $candidateIds ?: [];
    }

    // Caching experiment
    private function getCacheKey($tableName, $conditions)
    {
        return $tableName . "_" . md5(json_encode($conditions));
    }

    private function setCache($key, $data)
    {
        $this->cache[$key] = [
            "data" => $data,
            "expires" => time() + $this->cacheTTL,
        ];
    }

    private function getCache($key)
    {
        if (isset($this->cache[$key])) {
            if (time() < $this->cache[$key]["expires"]) {
                return $this->cache[$key]["data"];
            } else {
                unset($this->cache[$key]);
            }
        }
        return null;
    }

    private function clearCache($tableName = null)
    {
        if ($tableName) {
            foreach ($this->cache as $key => $value) {
                if (strpos($key, $tableName . "_") === 0) {
                    unset($this->cache[$key]);
                }
            }
        } else {
            $this->cache = [];
        }
    }
}
