<?php

namespace PdoModel\Engine;

use PDO;
use PdoModel\PdoModel;
use PdoModel\PdoModelException;

class PdoModelSqlite extends PdoModel
{
    public function insertUpdate(array $insertData, array $updateColumns = [], array $incrementColumns = []): int
    {
        if (!$this->isDriverSQLite()) {
            return parent::insertUpdate($insertData, $updateColumns);
        }
        if (empty($insertData) || (empty($updateColumns) && empty($incrementColumns))) {
            throw new PdoModelException('InsertUpdate arrays insertData and updateColumns or incrementColumns cant be empty');
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
        foreach ($incrementColumns as $column) {
            $updatePairs[] = "`{$column}` = `{$column}` + ?";
            if (!isset($insertData[$column])) {
                throw new PdoModelException("InsertUpdate incrementColumn '$column' not in array of insert data " . json_encode($insertData));
            }
            $values[] = $insertData[$column];
        }
        $sql = "INSERT INTO `" . $this->getTable() . "` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $markers) . ")"
            . " ON CONFLICT(" . static::PRIMARY_KEY . ") DO UPDATE SET " . implode(', ', $updatePairs);
        $this->execute($sql, $values);
        return $this->getLastInsertId();
    }


    public function insertUpdateBatch(array $insertRows, array $updateColumns = [], array $incrementColumns = []): bool
    {
        if (!$this->isDriverSQLite()) {
            return parent::insertUpdateBatch($insertRows, $updateColumns, $incrementColumns);
        }
        if (!empty($incrementColumns)) {
            foreach ($insertRows as $row) {
                $this->insertUpdate($row, incrementColumns: $incrementColumns);
            }
            return true;
        }
        foreach ($insertRows as $row) {
            $this->insertUpdate($row, $updateColumns);
        }
        return true;
    }

    protected function isDriverSQLite(): bool
    {
        return strtolower($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME)) == 'sqlite';
    }
}
