<?php

require __DIR__ . '/vendor/autoload.php';

$connection = \PdoModel\PdoFactory::createConnection([
    'host' => '127.0.0.1',
    'database' => 'notify',
    'username' => 'root',
    'password' => 'root',
]);

$model = new \PdoModel\PdoModel($connection);
$model->setTable('users');


$result = $model->select([], '', 100)->column(2);
var_dump($result);