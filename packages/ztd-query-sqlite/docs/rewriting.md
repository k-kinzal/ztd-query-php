# Sessions and simulated writes

`SqliteSessionFactory` is the entry point for custom adapters. Its `create()` method is an **instance method** accepting a core `ConnectionInterface` and `ZtdConfig`. The returned session owns the schema registry, shadow data, and virtual transaction state.

The [PDO adapter](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/ztd-query-pdo-adapter) supplies connection and statement adapters for ordinary PDO use. When implementing another adapter, provide the core connection and statement interfaces. This minimal example shows session creation without reflected tables:

```php
require 'vendor/autoload.php';

$connection = new class implements \ZtdQuery\Connection\ConnectionInterface {
    public function query(string $sql): \ZtdQuery\Connection\StatementInterface|false
    {
        return false;
    }
};
$session = (new \ZtdQuery\Platform\Sqlite\SqliteSessionFactory())->create(
    $connection,
    new \ZtdQuery\Config\ZtdConfig(),
);
$session->rewrite('SELECT 1')->sql(); // 'SELECT 1'
```

A real connection must execute schema queries and return statement adapters; the minimal connection above deliberately has no tables or query execution. Session creation reflects definitions, not physical table rows. Fixture writes populate the shadow state used by subsequent reads.

## Composing a rewrite pipeline

Use the lower-level components when the caller must own the `ShadowStore` and `TableDefinitionRegistry`. The following example shares those same objects between the rewriter and mutation resolver:

```php
$store = new \ZtdQuery\Shadow\ShadowStore();
$registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
$schemaParser = new \ZtdQuery\Platform\Sqlite\SqliteSchemaParser();
$schema = $schemaParser->parse('CREATE TABLE users(id INTEGER PRIMARY KEY, name TEXT)');
if ($schema === null) {
    throw new \RuntimeException('The fixture schema was not recognized');
}
$registry->register('users', $schema);
$store->set('users', [['id' => 1, 'name' => 'Alice']]);

$parser = new \ZtdQuery\Platform\Sqlite\SqliteParser();
$select = new \ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer();
$transformer = new \ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer(
    $parser,
    $select,
    new \ZtdQuery\Platform\Sqlite\Transformer\InsertTransformer($parser, $select),
    new \ZtdQuery\Platform\Sqlite\Transformer\UpdateTransformer($parser, $select),
    new \ZtdQuery\Platform\Sqlite\Transformer\DeleteTransformer($parser, $select),
);
$resolver = new \ZtdQuery\Platform\Sqlite\SqliteMutationResolver($store, $registry, $schemaParser, $parser);
$rewriter = new \ZtdQuery\Platform\Sqlite\SqliteRewriter(
    new \ZtdQuery\Platform\Sqlite\SqliteQueryGuard($parser),
    $store,
    $registry,
    $transformer,
    $resolver,
    $parser,
);
```

## Execute the result SELECT before applying a mutation

For a simulated write, `RewritePlan::sql()` selects the rows to change. Execute that SQL, apply the plan's mutation to the resulting rows, and commit rewrite state. Executing the original INSERT would modify the physical database.

```php
$pdo = new \PDO('sqlite::memory:', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY, name TEXT)');
$pdo->exec("INSERT INTO users VALUES (9000, 'physical')");

$plan = $rewriter->rewrite("INSERT INTO users VALUES (2, 'Bob')");
$rows = $pdo->query($plan->sql())->fetchAll(\PDO::FETCH_ASSOC);
$plan->mutation()?->apply($store, $rows);
$rewriter->commitRewriteState();

$read = $rewriter->rewrite('SELECT id, name FROM users ORDER BY id');
$pdo->query($read->sql())->fetchAll(\PDO::FETCH_ASSOC);
// [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]
$pdo->query('SELECT id, name FROM users')->fetchAll(\PDO::FETCH_ASSOC);
// [['id' => 9000, 'name' => 'physical']]
```

The low-level example covers ordinary DML. Prefer a session for adapter execution, result metadata, RETURNING projections, affected-row conventions, configuration policies, and virtual transactions.

## Multiple statements and failures

`rewrite()` accepts one statement. `rewriteMultiple()` returns a `MultiRewritePlan`, whose `plans()` are ordered `RewritePlan` objects. It does not execute them. For operations whose later SQL depends on an earlier mutation, split with `splitStatements()`, then rewrite, execute, and apply each statement in order.

Unsupported SQL raises `UnsupportedSqlException`; references absent from the supplied registry raise `UnknownSchemaException`. A guard classifying a statement does not guarantee that the complete statement is supported by the rewriter.

The public entry points documented here, the parser, query guard, schema parser/reflector, error classifier, and SELECT transformer retain their existing class names. Extracted collaborators under the parsing, schema, rewriting, and binding directories implement those entry points. Use the [generated API reference](https://k-kinzal.github.io/ztd-query-php/sqlite/k-kinzal/ztd-query-sqlite/) for signatures and executable examples, and the [SQLite specification](https://github.com/k-kinzal/ztd-query-php/blob/main/docs/sqlite-spec.md) for feature-specific behavior.
