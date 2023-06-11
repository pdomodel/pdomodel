# PdoModel - helper methods for MySql and Query Builder with raw PDO speed
```php
class YoutubeVideosModel extends \PdoModel\PdoModel
{
    protected $table = 'youtube_videos';
}

$connection = \PdoModel\PdoFactory::createConnection('127.0.0.1', 'dbname', 'username', 'password');
$youtubeVideosModel = new YoutubeVideosModel($connection);

$result = $youtubeVideosModel->select(['id', 'likes', 'url'])
            ->whereEqual('published', 1)
            ->where('likes', '>', 100)
            ->orderBy('likes desc')
            ->limit(100)
            ->offset(2000)
            ->groupBy('author')
            ->getAllRows();
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
Run tests
```shell
./vendor/bin/phpunit
```
Check code coverage with tests
```shell
php -dxdebug.mode=coverage ./vendor/bin/phpunit --coverage-text
```
