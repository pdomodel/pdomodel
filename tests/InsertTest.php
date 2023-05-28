<?php

use PdoModel\PdoModel;
use PHPUnit\Framework\TestCase;

class InsertTest extends TestCase
{
    public function testInsert(): void
    {
        $targetData = ['id' => 1, 'foo' => 'bar'];
        $testDb = new \PDO('sqlite::memory:');
        $testDb->exec('
            CREATE TABLE test_table (
                id INT AUTO_INCREMENT PRIMARY KEY,
                foo VARCHAR(255) NOT NULL
            )
        ');
        $modelSqlite = (new PdoModel($testDb))->setTable('test_table');
        $modelSqlite->insert($targetData);
        $resultData = $modelSqlite->select(['foo' => 'bar'])->first();
        $this->assertEquals($targetData, $resultData);
    }
}
