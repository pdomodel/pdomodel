# PdoModel
<b>Helper methods for MySql using PDO - fast alternative for Doctrine and etc</b>

```shell
composer require phpset/pdomodel
```

## Creating PDO connection
```php
$connection = \PdoModel\PdoFactory::createConnection('127.0.0.1', 'dbname', 'username', 'password');
// Or you can use any other PDO connection
$connection = new PDO();
```
And next pass this connection when creating model
```php
$db = new \PdoModel\PdoModel($connection);
```
For Symfony just add PDO to DI in service config
```yaml
PDO:
  class: \PDO
  factory: ['PdoModel\PdoFactory', 'createConnection']
  arguments: ['127.0.0.1', 'dbname', 'username', 'password']
```

## Your Model file example
```php
use PdoModel\PdoModel;

class YoutubeVideosModel extends PdoModel
{
    protected $table = 'youtube_videos';

    public function create(string $id, string $title, string $description, $createdAt): string
    {
        if ($this->find($id)) {
            return $id;
        }
        $this->insert([
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'created_at' => $createdAt,
        ]);
        return $id;
    }
}
```
