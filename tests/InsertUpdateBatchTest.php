<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PdoModel\Engine\PdoModelSqlite;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoModelSqlite::class)]
#[UsesClass(\PdoModel\Builder\CreateTableBuilder::class)]
#[UsesClass(\PdoModel\Builder\SelectorBuilder::class)]
class InsertUpdateBatchTest extends TestCase
{
    public function test(): void
    {
        $targetData = ['id' => 1, 'foo' => 'bar', 'count' => 1];

        $model = new class(new \PDO('sqlite::memory:')) extends PdoModelSqlite {
            const TABLE = 'test_table';
        };
        $model->createTable('test_table')
            ->column('id', autoIncrement: true, primaryKey: true)
            ->column('foo', type: 'string')
            ->column('count')
            ->execute();

        $model->insertUpdateBatch([$targetData, $targetData], incrementColumns: ['count']);
        $resultData = $model->select()->whereEqual('foo', 'bar')->getFirstRow();
        $this->assertEquals(['id' => 1, 'foo' => 'bar', 'count' => 2], $resultData);
    }
}
