<?php

namespace Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PdoModel\PdoModel;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoModel::class)]
#[UsesClass(\PdoModel\Builder\CreateTableBuilder::class)]
#[UsesClass(\PdoModel\Builder\SelectorBuilder::class)]
class InsertTest extends TestCase
{
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

    public function testInsertBatch(): void
    {
        $insertData = [
            ['foo' => 'cut by where', 'height' => 11, 'day' => 2],
            ['foo' => 'ok', 'height' => 10, 'day' => 2],
            ['foo' => 'ok', 'height' => 10, 'day' => 2],
        ];

        $model = new class(new \PDO('sqlite::memory:')) extends PdoModel {
            const TABLE = 'test_table';
        };
        $model->createTable('test_table')
            ->column('height')
            ->column('foo', type: 'string')
            ->column('day')
            ->execute();
        $model->insertBatch($insertData);

        $resultData = $model->select()->getAllRows();
        $this->assertEquals($insertData, $resultData);
    }
}
