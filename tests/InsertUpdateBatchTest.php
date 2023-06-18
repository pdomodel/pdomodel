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
        $targetData = ['foo' => 'bar', 'count' => 1];

        $model = new class(new \PDO('sqlite::memory:')) extends PdoModelSqlite {
            const TABLE = 'test_table';
        };
        $model->createTable('test_table')
            ->column('id', autoIncrement: true, primaryKey: true)
            ->column('foo', type: 'string', unique: true)
            ->column('count')
            ->execute();

        $model->insertUpdateBatch([$targetData], incrementColumns: ['count']);
        $resultData = $model->select(['foo', 'count'])->whereEqual('foo', 'bar')->getFirstRow();
        $this->assertEquals(['foo' => 'bar', 'count' => 1], $resultData);
    }
}
