# PdoModel - helper methods for MySql and Query Builder with raw PDO speed
```php
class YoutubeVideosModel extends \PdoModel\PdoModel
{
    const TABLE = 'youtube_videos';
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
composer require pdomodel/pdomodel
```

## Connection
You need to create usual PDO connection:
```php
$connection = new \PDO(
    "mysql:host=127.0.0.1;dbname=YOURDBNAME;charset=utf8mb4",
    "YOURUSER",
    "YOURPASSWORD",
    [
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ])
```

Example for creating PDO in Symfony service config:
```yaml
PDO:
  class: \PDO
  arguments:
    - "mysql:host=127.0.0.1;dbname=YOURDBNAME;charset=utf8mb4"
    - "YOURUSER"
    - "YOURPASSWORD"
    -
      !php/const PDO::ATTR_DEFAULT_FETCH_MODE: !php/const PDO::FETCH_ASSOC
      !php/const PDO::ATTR_ERRMODE: !php/const PDO::ERRMODE_EXCEPTION
```

### Tests
```shell
# run tests
./vendor/bin/phpunit

# Check coverage
php -dxdebug.mode=coverage ./vendor/bin/phpunit --coverage-text
```
