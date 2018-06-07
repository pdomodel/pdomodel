<?php

namespace PdoModel;

use \PDO;

class PdoFactory
{
    public static function createConnection(array $config)
    {
        $driver = isset($config['driver']) ? $config['driver'] : 'mysql';
        $charset = isset($config['charset']) ? $config['charset'] : 'utf8';

        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
            PDO::ATTR_CASE => PDO::CASE_LOWER,
        ];
        if (isset($config['options']) && is_array($config['options'])) {
            $options =  $config['options'] + $options;
        }

        return new PDO(implode(';',
            [
                $driver . ':host=' . $config['host'],
                'dbname=' . $config['database'],
                'charset=' . $charset,
            ]),
            $config['username'],
            $config['password'],
            $options
        );
    }
}