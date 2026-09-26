# Platform examples

Concrete construction and semantic result examples:

```php
$builder = new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
$schema = $builder->build('CREATE TABLE users (id INTEGER PRIMARY KEY)');
$schema->tables[0]->name // => 'users'
$schema->defaultSchema // => 'public'
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$schema->dialect->value // => 'postgresql'
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY)');
$statement = (new \SqlSemantics\Core\Binder($schema))->bind('SELECT id FROM users');
$statement->outputs[0]->expression->type->name // => 'integer'
$statement->outputs[0]->expression->nullability->value // => 'not-null'
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$schema->tables[0]->columns[0]->nullability->value // => 'not-null'
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$schema->tables[0]->schema // => 'public'
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$schema->tables[0]->constraints[0]->columns // => ['id']
```

```php
$type = new \SqlSemantics\Core\Type\TypeDescriptor(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'numeric', ['10', '2']);
$type->modifiers // => ['10', '2']
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$statement = (new \SqlSemantics\Core\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
$statement->outputs[1]->expression->nullExtendedBy // => ['j0']
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$statement = (new \SqlSemantics\Core\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
$statement->from->kind->value // => 'left'
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$statement = (new \SqlSemantics\Core\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
$statement->scopeId // => 's0'
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$statement = (new \SqlSemantics\Core\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
$statement->relations[1]->alias // => 'b'
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$statement = (new \SqlSemantics\Core\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
$statement->outputs[1]->expression->binding->relationId // => 'r1'
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$statement = (new \SqlSemantics\Core\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
$statement->orderBy[0]->descending // => true
```

```php
$schema = (new \SqlSemantics\Core\SchemaBuilder(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
$statement = (new \SqlSemantics\Core\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
$statement->outputs[1]->ordinal // => 1
```
