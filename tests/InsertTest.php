<?php

use PdoModel\PdoModel;
use PHPUnit\Framework\TestCase;

class InsertTest extends TestCase
{
    public function testInsert(): void
    {
        $db = new Pseudo\Pdo();
        $model = (new PdoModel($db))->setTable('test_table');

        $targetData = [['id' => 1, 'foo' => 'bar']];
        $db->mock("SELECT id FROM test_table WHERE foo=?", $targetData, ['bar']);
        $resultData = $model->select(['foo' => 'bar'], columns: ['id'])->all();

        $this->assertEquals($targetData, $resultData);
    }
}
