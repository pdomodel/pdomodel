<?php

use PdoModel\PdoModel;
use PHPUnit\Framework\TestCase;

class InsertTest extends TestCase
{
    /**
     * @covers \PdoModel\PdoModel::insert
     */
    public function testInsert(): void
    {
        $targetData = ['id' => 1, 'foo' => 'bar'];

        $testDb = new \PDO('sqlite::memory:');
        $model = (new PdoModel($testDb))->setTable('test_table');
        $model->createTable('test_table')
            ->column('id', autoIncrement: true, primaryKey: true)
            ->column('foo', type: 'string')
            ->execute();

        $model->insert($targetData);
        $resultData = $model->select(['foo' => 'bar'])->first();
        $this->assertEquals($targetData, $resultData);
    }
}
