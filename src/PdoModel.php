<?php

namespace PdoModel;

class PdoModel extends PdoHandler
{
    const INSERT = 'INSERT';
    const UPDATE = 'UPDATE';
    const SELECT = 'SELECT';
    const DELETE = 'DELETE';
    const INSERT_UPDATE = 'INSERT UPDATE';

    const MAX_PREPARED_STMT_COUNT = 60000;

    private $whereOperands = [
        '>',
        '<',
        '=',
        '!=',
        '>=',
        '<=',
        'IN',
        'in',
        'NOT IN',
        'not in',
        'LIKE',
        'like',
        'NOT LIKE',
        'not like',
        'IS',
        'is',
        'is not',
        'IS NOT',
    ];

    private $changeListenerCallback;

    // ------------------------------- Read methods ------------------------------------

    /**
     * @param array $whereCriteria
     * @param string|null $order
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $groupBy
     * @param array $columns
     * @return PdoResult
     * @throws \Exception
     */
    public function select(
        array $whereCriteria,
        ?string $order = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $groupBy = null,
        array $columns = []
    )
    {
        $criteria = $this->buildWhere($whereCriteria);
        $timeStart = microtime(true);

        if ($columns && count($columns)) {
            $columns = trim(implode(',', $columns));
        } else {
            $columns = '*';
        }

        if ($criteria['where']) {
            $sql = "SELECT {$columns} FROM {$this->getTable()} WHERE " . $criteria['where'];
        } else {
            $sql = "SELECT {$columns} FROM {$this->getTable()} ";
        }

        if ($groupBy) {
            $sql .= " GROUP BY " . $groupBy;
        }

        if ($order) {
            $sql .= " ORDER BY " . $order;
        }
        if ($offset && $limit) {
            $limit = (int)$offset . ',' . (int)$limit;
            $sql .= " LIMIT $limit";
        } elseif (!$offset && $limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $sth = $this->prepare($sql);
        $this->execute($sth, $criteria['values'] ?? []);

        $this->log(self::SELECT, $sql, $criteria['values'], $timeStart);

        return new PdoResult($sth);
    }

    /**
     * @param $query
     * @param array $params
     * @return PdoResult
     */
    public function selectRaw($query, $params = [])
    {
        $timeStart = microtime(true);
        $sth = $this->prepare($query);
        $this->execute($sth, $params ?? []);

        $this->log(self::SELECT, $query, $params, $timeStart);

        return new PdoResult($sth);
    }

    public function find($id)
    {
        $timeStart = microtime(true);
        $sql = "SELECT * FROM {$this->getTable()} WHERE {$this->getPrimaryKey()} = ? LIMIT 1";
        $sth = $this->prepare($sql);
        $this->execute($sth, [$id]);

        $this->log(self::SELECT, $sql, $id, $timeStart);

        return $sth->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array $whereCriteria
     * @return int
     * @throws \Exception
     */
    public function count(array $whereCriteria):int
    {
        $timeStart = microtime(true);
        $criteria = $this->buildWhere($whereCriteria);
        $sql = "SELECT count(*) FROM `{$this->getTable()}` WHERE " . $criteria['where'];
        $sth = $this->prepare($sql);

        $this->execute($sth, $criteria['values'] ?? []);
        $result = $sth->fetch(\PDO::FETCH_ASSOC);
        $this->log(self::SELECT, $sql, $criteria['values'], $timeStart);

        return (int)$result['count(*)'];
    }

    public function max($column = 'id', array $whereCriteria = []): int
    {
        $column = $this->getPrimaryKey();
        $timeStart = microtime(true);
        $criteria = $this->buildWhere($whereCriteria);

        $sql = "SELECT MAX({$column}) FROM {$this->getTable()}";
        if ($criteria['where']) {
            $sql .= " WHERE " . $criteria['where'];
        }

        $sth = $this->prepare($sql);
        $this->execute($sth, $criteria['values'] ?? []);
        $result = $sth->fetch();

        $this->log(self::SELECT, $sql, [], $timeStart);
        return (int)reset($result);
    }

    /**
     * @param string $column
     * @param array $whereCriteria
     * @return int
     * @throws \Exception
     */
    public function min(string $column = 'id', array $whereCriteria = []): ?int
    {
        $column = $this->getPrimaryKey();
        $timeStart = microtime(true);
        $criteria = $this->buildWhere($whereCriteria);

        $sql = "SELECT MIN({$column}) FROM {$this->getTable()}";
        if ($criteria['where']) {
            $sql .= " WHERE " . $criteria['where'];
        }

        $sth = $this->prepare($sql);
        $this->execute($sth, $criteria['values'] ?? []);
        $result = $sth->fetch();

        $this->log(self::SELECT, $sql, [], $timeStart);
        return (int)reset($result);
    }

    /**
     * @param $column
     * @return int
     * @throws \Exception
     */
    public function sum($column): int
    {
        $timeStart = microtime(true);
        $sql = "SELECT SUM({$column}) FROM {$this->getTable()}";
        $sth = $this->prepare($sql);
        $this->execute($sth);
        $result = $sth->fetch(\PDO::FETCH_ASSOC);

        $this->log(self::SELECT, $sql, [], $timeStart);

        return (int)reset($result);
    }

    // ------------------------------- Write methods ------------------------------------

    public function insert(array $data, bool $ignore = false): int
    {
        $timeStart = microtime(true);
        $ignoreSql = '';
        if ($ignore) {
            $ignoreSql = 'IGNORE ';
        }
        $insertData = $this->prepareInsertData($data);
        $sql = 'INSERT ' . $ignoreSql . "INTO `{$this->getTable()}` (" . $insertData['columns'] . ") VALUES (" . $insertData['params'] . ")";
        $sth = $this->prepare($sql);

        $sthRes = $this->execute($sth, $insertData['values'] ?? []);
        if ($sthRes === false) {
            throw new \Exception($sth->errorInfo()[2]);
        }
        $result = $this->getLastInsertId();

        if ($result && $record = $this->find($result)) {
            $this->changeListener($record[$this->getPrimaryKey()], $record);
        }
        $this->log(self::INSERT, $sql, $insertData['values'], $timeStart);

        return $result;
    }

    public function replace(array $data): int
    {
        $timeStart = microtime(true);
        $insertData = $this->prepareInsertData($data);
        $sql = "REPLACE INTO `{$this->getTable()}` (" . $insertData['columns'] . ") VALUES (" . $insertData['params'] . ")";
        $sth = $this->prepare($sql);

        $this->execute($sth, $insertData['values'] ?? []);
        $result = $this->getLastInsertId();

        if ($result) {
            $record = $this->find($result);
            $this->changeListener($record[$this->getPrimaryKey()], $record);
        }
        $this->log(self::INSERT, $sql, $insertData['values'], $timeStart);

        return $result;
    }

    /**
     * @param array $arraysOfData
     * @param bool $ignore
     * @return bool
     */
    public function insertBatch(array $arraysOfData, bool $ignore = false)
    {
        $keys = array_keys($arraysOfData[0]);
        $values = [];
        foreach ($arraysOfData as $data) {
            foreach ($data as $key => $value) {
                $values[] = $value;
            }
        }
        $res = $this->insertBatchRaw($keys, $values, $ignore);
        return $res;
    }

    private function insertBatchRaw(array $keys, array $values, bool $ignore = false)
    {
        // Hard but fast
        $keysCount = count($keys);
        $valuesCount = count($values);
        $valuesSqlCount = $valuesCount / $keysCount;
        $valuesSqlChunkSize = floor(self::MAX_PREPARED_STMT_COUNT / $keysCount);
        $valuesChunkSize = $valuesSqlChunkSize * $keysCount;
        $placeHolders = array_fill(0, $keysCount, '?');
        $placeHolders = implode(',', $placeHolders);
        $valuesSql = array_fill(0, $valuesSqlCount, '(' . $placeHolders . ')');
        $keys = implode(',', $keys);
        $table = $this->getTable();
        $valuesOffset = 0;

        $res = false;

        $ignoreSql = '';
        if ($ignore) {
            $ignoreSql = 'IGNORE ';
        }

        foreach (array_chunk($valuesSql, $valuesSqlChunkSize) as $valuesSqlPart) {
            $valuesPart = array_slice($values, $valuesOffset * $valuesSqlChunkSize * $keysCount, $valuesChunkSize);
            $valuesOffset++;
            $valuesSqlPart = implode(',', $valuesSqlPart);
            $sql = 'INSERT ' . $ignoreSql . "INTO `{$table}` ({$keys}) VALUES {$valuesSqlPart}";
            $sth = $this->prepare($sql);
            $res = $this->execute($sth, $valuesPart ?? []);

            $id = $this->getLastInsertId();
            if ($id) {
                $record = $this->find($id);
                $this->changeListener($id, $record);
            }
        }
        return $res;
    }

    public function insertUpdate(array $insert, array $update, bool $raw = false)
    {
        if (empty($insert) || empty($update)) {
            return false;
        }
        $timeStart = microtime(true);

        $insertData = $this->prepareUpdateData($insert, $raw);
        $updateData = $this->prepareUpdateData($update, $raw);
        $values = array_merge($insertData['values'], $updateData['values']);

        $sql = "INSERT INTO `{$this->getTable()}` SET {$insertData['set']} ON DUPLICATE KEY UPDATE {$updateData['set']}";
        $sth = $this->prepare($sql);
        if ($raw) {
            $result = $this->execute($sth);
        } else {
            $result = $this->execute($sth, $values ?? []);
        }

        $this->log(self::INSERT_UPDATE, $sql, $values, $timeStart);
        return $result;
    }

    /**
     * @param array $insert
     * @param array $updateKeys
     * @return bool
     * @throws \Exception
     */
    public function insertUpdateBatch(array $insert, array $updateKeys)
    {

        $insertKeys = array_keys($insert[0]);

        $insertValues = [];
        foreach ($insert as $data) {
            foreach ($data as $key => $value) {
                $insertValues[] = $value;
            }
        }

        $updateKeysRaw = [];
        foreach ($updateKeys as $data) {
            $updateKeysRaw[] = "{$data} = VALUES({$data})";
        }

        $res = $this->insertUpdateBatchRaw($insertKeys, $insertValues, $updateKeysRaw);
        return $res;
    }

    /**
     * @param array $insertKeys
     * @param array $insertValues
     * @param array $updateKeys
     * @return bool
     * @throws \Exception
     */
    private function insertUpdateBatchRaw(array $insertKeys, array $insertValues, array $updateKeys)
    {
        // Hard but fast
        $keysCount = count($insertKeys);
        $valuesCount = count($insertValues);
        $valuesSqlCount = $valuesCount / $keysCount;
        $valuesSqlChunkSize = floor(self::MAX_PREPARED_STMT_COUNT / $keysCount);
        $valuesChunkSize = $valuesSqlChunkSize * $keysCount;
        $placeHolders = array_fill(0, $keysCount, '?');
        $placeHolders = implode(',', $placeHolders);
        $valuesSql = array_fill(0, $valuesSqlCount, '(' . $placeHolders . ')');
        $keys = implode(',', $insertKeys);
        $table = $this->getTable();
        $valuesOffset = 0;
        $updateKeys = implode(',', $updateKeys);

        $res = false;
        foreach (array_chunk($valuesSql, $valuesSqlChunkSize) as $valuesSqlPart) {
            $valuesPart = array_slice($insertValues, $valuesOffset * $valuesSqlChunkSize * $keysCount, $valuesChunkSize);
            $valuesOffset++;
            $valuesSqlPart = implode(',', $valuesSqlPart);
            $sql = "INSERT INTO `{$table}` ({$keys}) VALUES {$valuesSqlPart} ON DUPLICATE KEY UPDATE {$updateKeys}";

            $sth = $this->prepare($sql);
            $res = $this->execute($sth, $valuesPart ?? []);

            $id = $this->getLastInsertId();
            if ($id) {
                $record = $this->find($id);
                $this->changeListener($id, $record);
            }
        }
        return $res;
    }

    /**
     * @param $id
     * @param $column
     * @param int $amount
     * @return bool
     * @throws \Exception
     */
    public function increment(int $id, $column, $amount = 1)
    {
        $record = $this->find($id);

        $timeStart = microtime(true);
        $sql = "UPDATE {$this->getTable()} SET {$column} = {$column} + {$amount} WHERE id = ?";
        $sth = $this->prepare($sql);
        $res = $this->execute($sth, [$id]);

        if ($record) {
            $this->changeListener($id, $record);
        }
        $this->log(self::UPDATE, $sql, [], $timeStart);
        return $res;
    }

    /**
     * @param $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function update(int $id, array $data)
    {
        if (empty($data)) {
            return false;
        }

        $record = $this->find($id);

        $timeStart = microtime(true);
        $updateData = $this->prepareUpdateData($data);
        $values = array_merge($updateData['values'], [$id]);

        $sql = "UPDATE `{$this->getTable()}` SET " . $updateData['set'] . " WHERE id = ?";
        $sth = $this->prepare($sql);
        $result = $this->execute($sth, $values ?? []);

        if ($record) {
            $this->changeListener($id, $record);
        }
        $this->log(self::UPDATE, $sql, $values, $timeStart);
        return $result;
    }

    /**
     * @param array $whereCriteria
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function updateWhere(array $whereCriteria, array $data)
    {
        if (empty($data) || empty($whereCriteria)) {
            return false;
        }

        $records = $this->select($whereCriteria)->all();

        $timeStart = microtime(true);
        $criteria = $this->buildWhere($whereCriteria);
        $updateData = $this->prepareUpdateData($data);
        $values = array_merge($updateData['values'], $criteria['values']);

        $sql = "UPDATE `{$this->getTable()}` SET " . $updateData['set'] . " WHERE " . $criteria['where'];
        $sth = $this->prepare($sql);
        $result = $this->execute($sth, $values ?? []);

        if ($records) {
            foreach ($records as $record) {
                $this->changeListener($record[$this->getPrimaryKey()], $record);
            }
        }
        $this->log(self::UPDATE, $sql, $values, $timeStart);
        return $result;
    }

    /**
     * @param $id
     * @return bool
     * @throws \Exception
     */
    public function delete(int $id)
    {
        $record = $this->find($id);

        $timeStart = microtime(true);
        $sql = "DELETE FROM {$this->getTable()} WHERE id = ?";
        $sth = $this->prepare($sql);
        $result = $this->execute($sth, [$id]);

        if ($record) {
            $this->changeListener($id, $record);
        }
        $this->log(self::DELETE, $sql, [$id], $timeStart);
        return $result;
    }

    /**
     * @param array $whereCriteria
     * @return bool
     * @throws \Exception
     */
    public function deleteWhere(array $whereCriteria): int
    {
        if (empty($whereCriteria)) {
            return false;
        }

        $records = $this->select($whereCriteria)->all();

        $criteria = $this->buildWhere($whereCriteria);
        $timeStart = microtime(true);
        $sql = "DELETE FROM {$this->getTable()} WHERE " . $criteria['where'];
        $sth = $this->prepare($sql);
        $this->execute($sth, $criteria['values'] ?? []);
        $result = (int)$sth->rowCount();

        if ($records) {
            foreach ($records as $record) {
                $this->changeListener($record[$this->getPrimaryKey()], $record);
            }
        }
        $this->log(self::DELETE, $sql, $criteria['values'], $timeStart);
        return $result;
    }


    // ------------------------------------- PRIVATE METHODS -------------------------------

    private function buildWhere(array $whereCriteria = [])
    {
        $pairs = [];
        $values = [];
        $criteria = [];

        if (!$whereCriteria || !count($whereCriteria)) {
            return [
                'where' => null,
                'values' => null,
            ];
        }

        foreach ($whereCriteria as $k => $v) {
            if (is_array($v)) {

                $key = $v[0];
                $operand = $v[1];
                $value = $v[2];

                if (!in_array($operand, $this->whereOperands)) {
                    throw new \Exception("Unsupported operand $operand in WHERE statement.");
                }

                if ($operand == 'IN' || $operand == 'in' || $operand == 'NOT IN' || $operand == 'not in') {
                    if (!is_array($value)) {
                        throw new \Exception("Value for $operand operand must be type of array only.");
                    }
                    $pairs[] = "{$key} {$operand} (" . implode(',', $value) . ")";
                    continue;
                }

                if ($operand == 'IS' || $operand == 'is' || $operand == 'IS NOT' || $operand == 'is not') {
                    $pairs[] = "{$key} {$operand} NULL";
                    continue;
                }

                $pairs[] = "{$key} {$operand} ?";
                $values[] = $value;
            } else {
                if (is_int($k)) { // array index
                    $pairs[] = $v;
                } else {
                    $pairs[] = "{$k} = ?";
                    $values[] = $v;
                }
            }
        }
        $criteria['where'] = implode(' AND ', $pairs);
        $criteria['values'] = $values;

        return $criteria;
    }

    private function prepareUpdateData(array $data, bool $raw = false)
    {
        $updateData = [];
        $pairs = [];
        $values = [];

        foreach ($data as $k => $v) {
            if ($raw) {
                $pairs[] = "$k = $v";
            } else {
                $pairs[] = "`{$k}` = ?";
            }
            $values[] = $v;
        }
        $updateData['set'] = implode(', ', $pairs);
        $updateData['values'] = $values;

        return $updateData;
    }

    private function prepareInsertData(array $data)
    {
        $insertData = [];
        $params = [];
        $values = [];
        $columns = [];

        foreach ($data as $k => $v) {
            $columns[] = "`$k`";
            $params[] = "?";
            $values[] = $v;
        }
        $insertData['values'] = $values;
        $insertData['columns'] = implode(', ', $columns);
        $insertData['params'] = implode(', ', $params);

        return $insertData;
    }

    protected function log($statement, $sql, $params, $timeStart)
    {
//        var_dump($statement, $sql, $params, $timeStart);
    }

    public function setChangeListener($callback)
    {
        $this->changeListenerCallback = $callback;
    }

    private function changeListener($id, $data = [])
    {
        if (is_callable($this->changeListenerCallback)) {
            call_user_func_array($this->changeListenerCallback, [&$id, &$data]);
        }
    }

    private function execute(\PDOStatement $sth, array $data = [])
    {
        foreach ($data as $item) {
            if (is_array($item)) {
                throw new \PDOException('Array ' . json_encode($item) . ' cant be statement value');
            }
        }

        try {
            $res = $sth->execute($data);
        } catch (\PDOException $e) {
            if ($e->getCode() != 23000) {
                error_log('Mysql execute error, data: ' . json_encode($data) . ' message: ' . $e->getMessage() . ', table: ' . static::getTable());
            }
            throw $e;
        }
        return $res;
    }
}
