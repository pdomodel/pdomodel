<?php

namespace PdoModel;

use \PDO;

class PdoFactory
{
    public static function createConnection(
        string $host = '127.0.0.1',
        string $database = 'defaultdb',
        string $user = 'root',
        string $password = '',
        array $options = [],
        int $port = 3306,
        string $charset = 'utf8',
        string $driver = 'mysql'
    )
    {
        $defaultOptions = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
            PDO::ATTR_CASE => PDO::CASE_LOWER,
        ];

        $options = array_merge($defaultOptions, $options);

        $connectionString = implode(';', [
            $driver . ':host=' . $host,
            'dbname=' . $database,
            'charset=' . $charset,
            'port=' . $port,
        ]);

        return new PDO($connectionString, $user, $password, $options);
    }
}
