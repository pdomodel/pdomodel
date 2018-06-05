<?php

require __DIR__ . '/vendor/autoload.php';


$aaa = 12;
$model = new \PdoModel\PdoModel(new PDO('mysql:127:0:0:1', 'root', 'root', []));
$model->setTable('aaaa');


$model->max('id', []);