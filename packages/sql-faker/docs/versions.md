# Supported versions

[Documentation](README.md) · [User guide](usage.md) · [API reference](api.md)

Pass a version tag as the second provider constructor argument. The exact tags
below are accepted; a bare version such as `'8.4'` is not a substitute. Omitting
the argument or passing `null` selects the default for that dialect.

| Provider | Version tag | Default |
| --- | --- | --- |
| `SqlFaker\MySqlProvider` | `mysql-5.6.51` | |
| `SqlFaker\MySqlProvider` | `mysql-5.7.44` | |
| `SqlFaker\MySqlProvider` | `mysql-8.0.44` | |
| `SqlFaker\MySqlProvider` | `mysql-8.1.0` | |
| `SqlFaker\MySqlProvider` | `mysql-8.2.0` | |
| `SqlFaker\MySqlProvider` | `mysql-8.3.0` | |
| `SqlFaker\MySqlProvider` | `mysql-8.4.7` | Yes |
| `SqlFaker\MySqlProvider` | `mysql-9.0.1` | |
| `SqlFaker\MySqlProvider` | `mysql-9.1.0` | |
| `SqlFaker\PostgreSqlProvider` | `pg-17.2` | Yes |
| `SqlFaker\SqliteProvider` | `sqlite-3.47.2` | Yes |

```php
require 'vendor/autoload.php';

$faker = \Faker\Factory::create();
$provider = new \SqlFaker\PostgreSqlProvider($faker, 'pg-17.2');
$faker->seed(7);

$sql = $provider->selectStatement(maxDepth: 3);
```

An unsupported tag raises `RuntimeException` during construction. Choose a
version matching the SQL parser or database under test. Some syntax-specific
methods need newer database features and cannot be used with every MySQL version.
