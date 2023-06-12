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

        $model = new class(new \PDO('sqlite::memory:')) extends PdoModel {
            const TABLE = 'test_table';
        };
        $model->createTable('test_table')
            ->column('id', autoIncrement: true, primaryKey: true)
            ->column('foo', type: 'string')
            ->execute();

        $model->insert($targetData);
        $resultData = $model->select()->whereEqual('foo', 'bar')->getFirstRow();
        $this->assertEquals($targetData, $resultData);
    }
}
