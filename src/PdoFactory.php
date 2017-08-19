<?php

namespace PdoModel;

use \PDO;

class PdoFactory
{
    public static function createConnection(array $config)
    {
        return new PDO(implode(';',
            [
                $config['driver'] . ':host=' . $config['host'],
                'dbname=' . $config['database'],
                'charset=' . $config['charset'],
            ]),
            $config['username'],
            $config['password'],
            isset($config['options']) ? $config['options'] : null
        );
    }
}