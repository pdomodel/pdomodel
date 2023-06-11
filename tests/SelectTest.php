<?php

use PdoModel\PdoModel;
use PHPUnit\Framework\TestCase;

class SelectTest extends TestCase
{
    /**
     * @covers \PdoModel\PdoModel::select
     */
    public function testSelect(): void
    {
        $insertData = [
            ['foo' => 'cut by where', 'height' => 11, 'day' => 2],
            ['foo' => 'ok', 'height' => 10, 'day' => 2],
            ['foo' => 'ok', 'height' => 10, 'day' => 2],
        ];
        $targetResult = [['foo' => 'ok', 'height' => 20]];


        $model = new PdoModel(new \PDO('sqlite::memory:'));
        $model->createTable('test_table')
            ->column('id', autoIncrement: true, primaryKey: true)
            ->column('height')
            ->column('foo', type: 'string')
            ->column('day')
            ->execute();
        $model->setTable('test_table');

        foreach ($insertData as $row) {
            $model->insert($row);
        }

        $result = $model->select(['foo', 'sum(height) as height'])
            ->whereEqual('height', 10)
            ->orderBy('id desc')
            ->limit(3)
            ->offset(0)
            ->groupBy('day')
            ->getAllRows();
        $this->assertEquals($targetResult, $result);
    }
}
