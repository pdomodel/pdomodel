# PdoModel
<b>Helper methods for MySql by PDO </b>

```shell
composer require phpset/pdomodel
```

```php
$connection = \PdoModel\PdoFactory::createConnection([
    'host' => '127.0.0.1',
    'password' => 'somepass',
    'username' => 'user',
    'database' => 'dbname',
    'port' => '25060',
    'options' => [
        PDO::ATTR_TIMEOUT => 1,
    ],
]);

$db = new \PdoModel\PdoModel($connection);
$db->setTable('records');
$db->insert(['id'=>1, 'name'=>'first record']);

```



## Selecting

### select
(array $whereCriteria, $order = '', $limit = false, $offset = false, $columns = [])

### selectRaw
($query, $params = [])

### find
(id)
...
### count
(array $whereCriteria)

### max
($column = 'id')

### min
($column)

### sum
($column)

## Inserting
### insert
(array $data)

### insertBatch(array $arraysOfData)
(array $arraysOfData)

### insertUpdate
(array $insert, array $update)

## Updating
### update
($id, array $data)

### updateWhere
(array $whereCriteria, array $data)

### increment
($id, $column, $amount = 1)

## Deleting
### delete
($id)

### deleteWhere
(array $whereCriteria)

## Extra
### log
($statement, $sql, $params, $timeStart)

### setChangeListener
($callback)

