<?php

namespace PdoModel\Engine;

use PDO;
use PdoModel\PdoModel;
use PdoModel\PdoModelException;

class PdoModelSqlite extends PdoModel
{
    public function insertUpdate(array $insertData, array $updateColumns): int
    {
        if (!$this->isDriverSQLite()) {
            return parent::insertUpdate($insertData, $updateColumns);
        }
        if (empty($insertData) || empty($updateColumns)) {
            throw new PdoModelException('InsertUpdate arrays insertData and updateColumns cant be empty');
        }
        $updatePairs = [];
        $values = [];
        $markers = [];
        $columns = [];

        foreach ($insertData as $k => $v) {
            $columns[] = "`$k`";
            $markers[] = "?";
            $values[] = $v;
        }
        foreach ($updateColumns as $column) {
            $updatePairs[] = "`{$column}` = ?";
            if (!isset($insertData[$column])) {
                throw new PdoModelException("InsertUpdate updateColumn '$column' not in array of insert data " . json_encode($insertData));
            }
            $values[] = $insertData[$column];
        }
        // TODO check SQL
        $sql = "INSERT INTO `" . $this->getTable() . "` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $markers) . ")"
            . " ON CONFLICT(" . static::PRIMARY_KEY . ") DO UPDATE SET" . implode(', ', $updatePairs);
        $this->execute($sql, $values);
        return $this->getLastInsertId();
    }


    public function insertUpdateBatch(array $insertRows, array $updateColumns = [], array $incrementColumns = []): bool
    {
        if (!$this->isDriverSQLite()) {
            return parent::insertUpdateBatch($insertRows, $updateColumns, $incrementColumns);
        }
        if (empty($incrementColumns)) {
            foreach ($insertRows as $row) {
                $this->insertUpdate($row, $updateColumns);
            }
            return true;
        } else {
            foreach ($insertRows as $row) {
                // todo
            }
            return true;
        }
    }

    protected function isDriverSQLite(): bool
    {
        return strtolower($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME)) == 'sqlite';
    }
}
