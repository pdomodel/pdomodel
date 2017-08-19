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
        '>=',
        '<=',
    ];

    private $changeListenerCallback;

    // ------------------------------- Read methods ------------------------------------

    /**
     * @param array $whereCriteria
     * @param string $order (example: 'id DESC')
     * @param bool $limit
     * @param bool $offset
     * @return PdoResult
     * @throws \Exception
     */
    public function select(array $whereCriteria, $order = '', $limit = false, $offset = false)
    {
        $criteria = $this->buildWhere($whereCriteria);
        $timeStart = microtime(true);

        $sql = "SELECT * FROM {$this->getTable()} WHERE " . $criteria['where'];

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
        $this->execute($sth, $criteria['values']);

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
        $this->execute($sth, $params);

        $this->log(self::SELECT, $query, $params, $timeStart);

        return new PdoResult($sth);
    }

    /**
     * @param $id
     * @return array
     * @throws \Exception
     */
    public function find($id)
    {
        $timeStart = microtime(true);
        $sql = "SELECT * FROM {$this->getTable()} WHERE id = ? LIMIT 1";
        $sth = $this->prepare($sql);
        $this->execute($sth, [(int)$id]);

        $this->log(self::SELECT, $sql, $id, $timeStart);

        return $sth->fetch();
    }

    /**
     * @param array $whereCriteria
     * @return int
     * @throws \Exception
     */
    public function count(array $whereCriteria)
    {
        $timeStart = microtime(true);
        $criteria = $this->buildWhere($whereCriteria);
        $sql = "SELECT count(*) FROM `{$this->getTable()}` WHERE " . $criteria['where'];
        $sth = $this->prepare($sql);

        $this->execute($sth, $criteria['values']);
        $result = $sth->fetch();
        $this->log(self::SELECT, $sql, $criteria['values'], $timeStart);

        return (int)$result['count(*)'];
    }

    /**
     * @param string $column
     * @return int
     * @throws \Exception
     */
    public function max($column = 'id')
    {
        $timeStart = microtime(true);
        $sql = "SELECT MAX({$column}) FROM {$this->getTable()}";
        $sth = $this->prepare($sql);
        $this->execute($sth);
        $result = $sth->fetch();

        $this->log(self::SELECT, $sql, [], $timeStart);

        return (int)reset($result);
    }

    /**
     * @param $column
     * @return int
     * @throws \Exception
     */
    public function min($column)
    {
        $timeStart = microtime(true);
        $sql = "SELECT MIN({$column}) FROM {$this->getTable()}";
        $sth = $this->prepare($sql);
        $this->execute($sth);
        $result = $sth->fetch();

        $this->log(self::SELECT, $sql, [], $timeStart);

        return (int)reset($result);
    }

    /**
     * @param $column
     * @return int
     * @throws \Exception
     */
    public function sum($column)
    {
        $timeStart = microtime(true);
        $sql = "SELECT SUM({$column}) FROM {$this->getTable()}";
        $sth = $this->prepare($sql);
        $this->execute($sth);
        $result = $sth->fetch();

        $this->log(self::SELECT, $sql, [], $timeStart);

        return (int)reset($result);
    }

    // ------------------------------- Write methods ------------------------------------

    /**
     * @param array $data
     * @return bool|int
     * @throws \Exception
     */
    public function insert(array $data)
    {
        $timeStart = microtime(true);
        $insertData = $this->prepareInsertData($data);
        $sql = "INSERT INTO `{$this->getTable()}` (" . $insertData['columns'] . ") VALUES (" . $insertData['params'] . ")";
        $sth = $this->prepare($sql);

        $this->execute($sth, $insertData['values']);
        $result = $this->getLastInsertId();

        $record = $this->find($result);
        $this->changeListener($record['id'], $record);
        $this->log(self::INSERT, $sql, $insertData['values'], $timeStart);

        return (int)$result;
    }

    /**
     * @param array $arraysOfData
     * @return bool
     */
    public function insertBatch(array $arraysOfData)
    {
        $keys = array_keys($arraysOfData[0]);
        $values = [];
        foreach ($arraysOfData as $data) {
            foreach ($data as $key => $value) {
                $values[] = $value;
            }
        }
        $res = $this->insertBatchRaw($keys, $values);
        return $res;
    }

    private function insertBatchRaw(array $keys, array $values)
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
        foreach (array_chunk($valuesSql, $valuesSqlChunkSize) as $valuesSqlPart) {
            $valuesPart = array_slice($values, $valuesOffset * $valuesSqlChunkSize * $keysCount, $valuesChunkSize);
            $valuesOffset++;
            $valuesSqlPart = implode(',', $valuesSqlPart);
            $sql = "INSERT INTO `{$table}` ({$keys}) VALUES {$valuesSqlPart}";
            $sth = $this->prepare($sql);
            $res = $this->execute($sth, $valuesPart);

            $id = $this->getLastInsertId();
            $record = $this->find($id);
            $this->changeListener($id, $record);
        }
        return $res;
    }

    /**
     * Run INSERT OR UPDATE query
     *
     * @param array $insert Insert data
     * @param array $update Update data
     *
     * @return bool|\PDOStatement
     */
    public function insertUpdate(array $insert, array $update)
    {
        if (empty($insert) || empty($update)) {
            return false;
        }
        $timeStart = microtime(true);

        $insertData = $this->prepareUpdateData($insert);
        $updateData = $this->prepareUpdateData($update);
        $values = array_merge($insertData['values'], $updateData['values']);

        $sql = "INSERT INTO `{$this->getTable()}` SET {$insertData['set']} ON DUPLICATE KEY UPDATE {$updateData['set']}";
        $sth = $this->prepare($sql);
        $result = $this->execute($sth, $values);
        
        $this->log(self::INSERT_UPDATE, $sql, $values, $timeStart);
        return $result;
    }

    /**
     * @param $id
     * @param $column
     * @param int $amount
     * @return bool
     * @throws \Exception
     */
    public function increment($id, $column, $amount = 1)
    {
        $record = $this->find($id);

        $timeStart = microtime(true);
        $sql = "UPDATE {$this->getTable()} SET {$column} = {$column} + {$amount} WHERE id = ?";
        $sth = $this->prepare($sql);
        $res = $this->execute($sth, [(int)$id]);

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
    public function update($id, array $data)
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
        $result = $this->execute($sth, $values);

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
        $result = $this->execute($sth, $values);

        if ($records) {
            foreach ($records as $record) {
                $this->changeListener($record['id'], $record);
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
    public function delete($id)
    {
        $record = $this->find($id);

        $timeStart = microtime(true);
        $sql = "DELETE FROM {$this->getTable()} WHERE id = ?";
        $sth = $this->prepare($sql);
        $result = $this->execute($sth, [(int)$id]);

        if ($record) {
            $this->changeListener($id, $record);
        }
        $this->log(self::DELETE, $sql, [(int)$id], $timeStart);
        return $result;
    }

    /**
     * @param array $whereCriteria
     * @return bool
     * @throws \Exception
     */
    public function deleteWhere(array $whereCriteria)
    {
        if (empty($whereCriteria)) {
            return false;
        }

        $records = $this->select($whereCriteria)->all();

        $criteria = $this->buildWhere($whereCriteria);
        $timeStart = microtime(true);
        $sql = "DELETE FROM {$this->getTable()} WHERE " . $criteria['where'];
        $sth = $this->prepare($sql);
        $result = $this->execute($sth, $criteria['values']);

        if ($records) {
            foreach ($records as $record) {
                $this->changeListener($record['id'], $record);
            }
        }
        $this->log(self::DELETE, $sql, $criteria['values'], $timeStart);
        return $result;
    }


    // ------------------------------------- PRIVATE METHODS -------------------------------

    private function buildWhere(array $whereCriteria)
    {
        $pairs = [];
        $values = [];
        $criteria = [];

        foreach ($whereCriteria as $k => $v) {
            if (is_array($v)) {

                $key = $v[0];
                $operand = $v[1];
                $value = $v[2];

                if (!in_array($operand, $this->whereOperands)) {
                    throw new \Exception("Unsupported operand $operand in WHERE statement.");
                }
                $pairs[] = "`{$key}` {$operand} ?";
                $values[] = $value;
            } else {
                $pairs[] = "`{$k}` = ?";
                $values[] = $v;
            }
        }
        $criteria['where'] = implode($pairs, ' AND ');
        $criteria['values'] = $values;

        return $criteria;
    }

    private function prepareUpdateData(array $data)
    {
        $updateData = [];
        $pairs = [];
        $values = [];

        foreach ($data as $k => $v) {
            $pairs[] = "`{$k}` = ?";
            $values[] = $v;
        }
        $updateData['set'] = implode($pairs, ', ');
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

    private function log($statement, $sql, $params, $timeStart)
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
    
    private function execute(\PDOStatement $sth, $data = null)
    {
        try {
            $res = $sth->execute($data);
        } catch (\PDOException $e) {
            error_log('Mysql execute error, data: ' . json_encode($data) . ' message: ' . $e->getMessage() . ', table: ' . static::getTable());
            throw $e;
        }
        return $res;
    }
}