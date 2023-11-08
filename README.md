# PdoModel - helper methods for MySql and Query Builder with raw PDO speed
Example of using:
```php
class YoutubeVideosModel extends \PdoModel\PdoModel
{
    const TABLE = 'youtube_videos';

    protected function create($title, $src) {
        $this->insert(['title' => $title, 'src' => $src]);
    }
}
$youtubeVideosModel = new YoutubeVideosModel(new \Pdo());

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
```shell
composer require phpset/pdomodel
```

You need to create a usual PDO connection:
```php
$connection = new \PDO(
    "mysql:host=127.0.0.1;dbname=YOURDBNAME;charset=utf8mb4",
    "YOURUSER",
    "YOURPASSWORD",
    [
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ]
);
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

## Tests
```shell
# run tests
./vendor/bin/phpunit

# Check coverage
php -dxdebug.mode=coverage ./vendor/bin/phpunit --coverage-text
```
