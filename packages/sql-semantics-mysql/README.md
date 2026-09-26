# SQL Semantics for MySQL

Typed MySQL statement models, SQL reconstruction, and schema-dependent binding.
Requires PHP 8.1+.

```sh
composer require k-kinzal/sql-semantics-mysql
```

This installs the shared `k-kinzal/sql-semantics` runtime and `k-kinzal/sql-parser`.
Other SQL Semantics database packages are optional and are not installed.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');
$statement = $semantics->analyze('INSERT INTO users (id) VALUES (1)');
$sql = $statement->toString();
```

Omit the grammar version to select the parser's default. The same dialect enum
can be passed to `SqlSemantics\Core\SchemaBuilder` for schema-dependent binding.
See the [shared runtime documentation](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics)
for model construction, binding, and the distinction between those APIs.

`src/` contains this database's platform and dialect entry point.
`resources/models/` contains generated PHP types, and `resources/mapping/`
contains a construction map for every supported grammar release. Statement
models reconstruct SQL from their fields and do not retain parser trees or
original statements. Deptrac checks this boundary, including all generated types.

## Development

Run these commands from this directory or from its split repository:

```sh
composer install
composer build:models:check
composer lint
composer test
composer fuzz:smoke
composer bench:quick
```

`composer build:models` regenerates this database's models using the common
runtime's `vendor/bin/build-models.php`. It compiles all supported releases of
this database together. Output is written to `resources/`; caches and staging
stay under this package's `build/`. Generated files are committed and are not
built during installation.

See [fuzz testing](fuzz/README.md) for seed replay and longer runs.

## License

MIT. See [LICENSE](LICENSE).
