# PdoModel - helper methods for MySql and Query Builder with raw PDO speed
```php
class YoutubeVideosModel extends \PdoModel\PdoModel
{
    protected $table = 'youtube_videos';
}

$connection = \PdoModel\PdoFactory::createConnection('127.0.0.1', 'dbname', 'username', 'password');
$youtubeVideosModel = new YoutubeVideosModel($connection);

$result = $youtubeVideosModel->select(
    whereCriteria: [
        ['published', 1],
        ['likes', '>', 100],
    ],
    order: 'likes desc',
    limit: 100,
    offset: 2000,
    groupBy: 'author',
    columns: ['id', 'likes', 'url']
)->all();
var_dump($result);
```

## Setup
Install via composer
```shell
composer require phpset/pdomodel
```

Create connection. Or you can provide to PdoModel constructor any other PDO connection
```php
$connection = \PdoModel\PdoFactory::createConnection('127.0.0.1', 'dbname', 'username', 'password');
```

For Symfony just add PDO to DI in service config
```yaml
PDO:
  class: \PDO
  factory: ['PdoModel\PdoFactory', 'createConnection']
  arguments: ['127.0.0.1', 'dbname', 'username', 'password']
```

### Tests
```shell
./vendor/bin/phpunit tests
```
