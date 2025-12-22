# sql-faker

A Faker provider for generating syntactically valid MySQL SQL statements based on MySQL's official Bison grammar (sql_yacc.yy).

## Requirements

- PHP 8.1+

## Usage

```php
use Faker\Factory;
use SqlFaker\MySqlProvider;

$faker = Factory::create();
$faker->addProvider(new MySqlProvider($faker));

// Generate random SQL statements
$faker->sql();              // Any valid MySQL statement
$faker->selectStatement();  // SELECT statement
$faker->insertStatement();  // INSERT statement
$faker->updateStatement();  // UPDATE statement
$faker->deleteStatement();  // DELETE statement

// DDL statements
$faker->createTableStatement();
$faker->alterTableStatement();
$faker->dropTableStatement();

// SQL fragments
$faker->expr();           // Expression
$faker->whereClause();    // WHERE clause
$faker->subquery();       // Subquery
$faker->withClause();     // CTE (WITH clause)
```

### Specifying MySQL Version

```php
// Use specific MySQL version (default: mysql-8.4.7)
$faker->addProvider(new MySqlProvider($faker, 'mysql-5.7.44'));
```

Supported versions: `mysql-5.6.51`, `mysql-5.7.44`, `mysql-8.0.44`, `mysql-8.1.0`, `mysql-8.2.0`, `mysql-8.3.0`, `mysql-8.4.7`, `mysql-9.0.1`, `mysql-9.1.0`

### Controlling Complexity

Use `maxDepth` to control the complexity of generated SQL:

```php
$faker->selectStatement(maxDepth: 6);  // Simpler SELECT
$faker->selectStatement();              // Complex SELECT (unlimited depth)
```