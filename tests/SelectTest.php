<?php

namespace Tests;

use PdoModel\PdoModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoModel::class)]
#[UsesClass(\PdoModel\Builder\CreateTableBuilder::class)]
#[UsesClass(\PdoModel\Builder\SelectorBuilder::class)]
class SelectTest extends TestCase
{
    public function testSelect(): void
    {
        $insertData = [
            ['foo' => 'cut by where', 'height' => 11, 'day' => 2],
            ['foo' => 'ok', 'height' => 10, 'day' => 2],
            ['foo' => 'ok', 'height' => 10, 'day' => 2],
        ];
        $targetResult = [['foo' => 'ok', 'height' => 20]];


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

        $result = $model->select(['foo', 'sum(height) as height'])
            ->whereEqual('height', 10)
            ->orderBy('id desc')
            ->limit(3)
            ->offset(0)
            ->groupBy('day')
            ->collate('utf8mb4_0900_ai_ci')
            ->getAllRows();
        $this->assertEquals($targetResult, $result);
    }
}
