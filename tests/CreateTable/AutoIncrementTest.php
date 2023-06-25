<?php

namespace Tests\CreateTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PdoModel\PdoModel;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\PdoModel\Builder\CreateTableBuilder::class)]
#[UsesClass(\PdoModel\PdoModel::class)]
#[UsesClass(\PdoModel\Builder\SelectorBuilder::class)]
class AutoIncrementTest extends TestCase
{
    public function testSqlite(): void
    {
        $model = (new PdoModel(new \PDO('sqlite::memory:')))->setTable('test_table');
        $model->createTable('test_table')
            ->column('id', autoIncrement: true, primaryKey: true)
            ->column('foo', type: 'string')
            ->execute();
        $model->insert(['foo' => 1]);
        $model->insert(['foo' => 2]);
        $model->insert(['foo' => 3]);

        $actualResult = $model->select()->getAllRows();

        $this->assertEquals([
            ['id' => 1, 'foo' => 1],
            ['id' => 2, 'foo' => 2],
            ['id' => 3, 'foo' => 3],
        ], $actualResult);
    }

}
