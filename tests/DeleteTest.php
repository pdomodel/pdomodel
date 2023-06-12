<?php

use PdoModel\PdoModel;
use PHPUnit\Framework\TestCase;

class DeleteTest extends TestCase
{
    /**
     * @covers \PdoModel\PdoModel::delete
     */
    public function testDelete(): void
    {
        $insertData = [
            ['id' => 1, 'foo' => 'cut by where', 'height' => 11, 'day' => 2],
            ['id' => 2, 'foo' => 'ok', 'height' => 10, 'day' => 2],
            ['id' => 3, 'foo' => 'ok', 'height' => 10, 'day' => 2],
        ];
        $targetData = [
            ['id' => 1, 'foo' => 'cut by where', 'height' => 11, 'day' => 2],
            ['id' => 3, 'foo' => 'ok', 'height' => 10, 'day' => 2],
        ];

        $model = new class(new \PDO('sqlite::memory:')) extends PdoModel {
            const TABLE = 'test_table';
        };
        $model->createTable('test_table')
            ->column('id', autoIncrement: true, primaryKey: true)
            ->column('height')
            ->column('foo', type: 'string')
            ->column('day')
            ->execute();

        foreach ($insertData as $row) {
            $model->insert($row);
        }

        $model->delete(2);
        $resultData = $model->select()->getAllRows();
        $this->assertEquals($targetData, $resultData);

        $this->assertFalse($model->delete(4));
    }
}
