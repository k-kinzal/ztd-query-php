<?php

declare(strict_types=1);

$installed = $argv[1] ?? '/stage';
$catalog = $argv[2] ?? '/roles.json';
require $installed . '/vendor/autoload.php';

$roles = json_decode(file_get_contents($catalog), true, 512, JSON_THROW_ON_ERROR);
function observeEnum(string $name): array
{
    $reflection = new ReflectionEnum($name);
    if ($reflection->getBackingType()?->getName() !== 'string') {
        throw new RuntimeException('An installed IR enum has an unexpected backing type.');
    }
    $listed = $name::cases();
    $cases = [];
    foreach ($reflection->getCases() as $position => $declaration) {
        $case = $declaration->getValue();
        $direct = constant($name . '::' . $case->name);
        $selected = $name::from($case->value);
        $optional = $name::tryFrom($case->value);
        $restored = unserialize(serialize($case), ['allowed_classes' => [$name]]);
        if ($case !== $direct || $case !== ($listed[$position] ?? null) || $case !== $selected
            || $case !== $optional || $case !== $restored) {
            throw new RuntimeException('An installed enum publication route returned another value.');
        }
        $cases[] = [$case->name, $case->value];
    }
    if (count($listed) !== count($cases)) {
        throw new RuntimeException('An installed enum exposes an extra case.');
    }
    $unknown = '__ztd_invalid_enum_value';
    while ($name::tryFrom($unknown) !== null) {
        $unknown .= '_';
    }
    try {
        $name::from($unknown);
    } catch (ValueError) {
        return ['backing' => 'string', 'cases' => $cases, 'unknownValue' => $unknown, 'unknownRejected' => true];
    }

    throw new RuntimeException('An installed enum accepts an unknown backing value.');
}

function observeMysqlVarchar(SqlSemantics\Platform\MySql\Semantics $semantics): array
{
    $context = $semantics->context([
        'CREATE TABLE app.native_source (id INT, label VARCHAR(8)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
        'CREATE TABLE app.native_target (id INT, label VARCHAR(2) COLLATE utf8mb4_bin NOT NULL)',
    ]);
    $projection = $semantics->analyze('SELECT label COLLATE utf8mb4_bin AS v, s.label AS v FROM app.native_source s', $context);
    $grouped = $semantics->analyze('SELECT MIN(label) AS low, MAX(DISTINCT s.label) AS high FROM app.native_source s', $context);
    $write = $semantics->analyze('INSERT INTO app.native_target (label, id) SELECT DISTINCT s.label COLLATE utf8mb4_bin AS v, s.id AS n FROM app.native_source s', $context);
    $joined = $semantics->analyze('SELECT q.message AS message, q.id AS local_id FROM app.native_source p LEFT JOIN '
        . '(SELECT label AS message, id FROM app.native_source) q ON p.id = q.id', $context);
    $composed = $semantics->analyze('SELECT q.label AS item, q.id AS item FROM (SELECT id, label FROM app.native_source) q', $context);
    if (!$projection instanceof SqlSemantics\Platform\MySql\Ir\Select
        || !$grouped instanceof SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedSelect
        || !$write instanceof SqlSemantics\Platform\MySql\Ir\Mutation\InsertQuery
        || !$composed instanceof SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection
        || !$joined instanceof SqlSemantics\Platform\MySql\Ir\Select
        || !$joined->source instanceof SqlSemantics\Platform\MySql\Ir\From\Join) {
        throw new RuntimeException('The installed analyzer lost a checked MySQL VARCHAR root.');
    }
    $derived = $joined->source->inputs()[1];
    if (!$derived instanceof SqlSemantics\Platform\MySql\Ir\From\DerivedSource) {
        throw new RuntimeException('The installed analyzer lost the derived MySQL occurrence.');
    }
    $explicit = $projection->columns[0]->expression;
    $implicit = $projection->columns[1]->expression;
    $low = $grouped->columns[0]->value;
    $high = $grouped->columns[1]->value;
    $destination = $write->target->columnTypes[0];
    $inherited = $projection->source->columnTypes[0];
    $first = $write->query->columns[0]->expression;
    $second = $write->query->columns[1]->expression;
    $local = $joined->columns[0]->expression;
    $native = $derived->columnType(0);
    return [
        'projection' => [$explicit::class, $explicit->operand::class, $explicit->operand->slot, $implicit::class, $implicit->slot,
            $inherited->collation->value, $inherited->length, $projection->toString()],
        'group' => [$low->operator->value, $low->argument::class, $low->argument->slot, $low->distinct,
            $high->operator->value, $high->argument::class, $high->argument->slot, $high->distinct, $grouped->toString()],
        'write' => [array_map(static fn ($column) => $column->value, $write->target->columns),
            $destination->length, $destination->collation->value, $destination->nullable, $write->target->columnTypes[1]->value,
            $write->query->distinct, $first::class, $first->operand->slot, $second::class, $second->slot, $write->toString()],
        'derived' => [$local::class, $local->occurrence, $local->slot, $native->collation->value, $native->length,
            $native === $derived->definition->source->columnTypes[0], $derived->columnType(1)->value,
            $derived->columnType(2), $joined->toString()],
        'local' => [array_map(static fn ($column) => [$column->slot, $column->name->value], $composed->columns),
            $composed->definition->columns[1]->expression::class, $composed->toString()],
    ];
}

function observeMysqlVarcharDeclaration(SqlSemantics\Platform\MySql\Semantics $semantics): array
{
    $query = "CREATE TABLE IF NOT EXISTS app.varchar_decl (n SMALLINT NOT NULL DEFAULT -7, label VARCHAR(1) DEFAULT 'é  ')"
        . ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
    $analyzed = $semantics->analyze($query);
    $space = SqlSemantics\Platform\MySql\Ir\From\TextCollation::Utf8mb4Bin;
    $name = new SqlSemantics\Platform\MySql\Ir\Identifier('label');
    $storage = new SqlSemantics\Platform\MySql\Ir\Schema\CharacterStorage(1, $space);
    $value = new SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral('é  ');
    $column = new SqlSemantics\Platform\MySql\Ir\Schema\TableColumn($name, $storage, true, $value);
    $n = new SqlSemantics\Platform\MySql\Ir\Schema\TableColumn(
        new SqlSemantics\Platform\MySql\Ir\Identifier('n'),
        SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Small, false, new SqlSemantics\Platform\MySql\Ir\IntegerLiteral('-7')
    );
    $location = new SqlSemantics\Platform\MySql\Ir\Schema\TableName(
        new SqlSemantics\Platform\MySql\Ir\Identifier('app'), new SqlSemantics\Platform\MySql\Ir\Identifier('varchar_decl')
    );
    $direct = new SqlSemantics\Platform\MySql\Ir\Schema\CreateTable($location, [$n, $column], true, defaultCollation: $space);
    $type = $analyzed->columns[1]->storage;

    return [
        $analyzed::class, $analyzed->target->schema->value, $analyzed->target->table->value, $analyzed->ifNotExists,
        $analyzed->defaultCollation->value, $analyzed->columns[0]->storage->value, $type::class, $type->length,
        $type->collation->value, $analyzed->columns[1]->nullable, $analyzed->columns[1]->default->value,
        $analyzed->toString(), $direct->toString(), $direct->profileId(),
    ];
}

function observeMysqlSignedDeclaration(SqlSemantics\Platform\MySql\Semantics $semantics): array
{
    $value = $semantics->analyze('CREATE TABLE app.signed_decl (a TINYINT DEFAULT -128, b MEDIUMINT DEFAULT 8388607, '
        . 'c BIGINT NOT NULL DEFAULT 9223372036854775807, d BIGINT DEFAULT -9223372036854775808)');
    $rejected = null;
    try {
        $semantics->analyze('CREATE TABLE app.signed_decl (a TINYINT DEFAULT 128)');
    } catch (SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction $error) {
        $rejected = $error->getMessage();
    }

    return [array_map(static fn ($field): array => [
        $field->storage->value, $field->storage->bytes(), $field->nullable, $field->default::class, $field->default->decimal,
    ], $value->columns), $value->toString(), $rejected];
}

function observeMysqlPrimaryKey(SqlSemantics\Platform\MySql\Semantics $semantics): array
{
    $value = $semantics->analyze('CREATE TABLE app.key_decl (lo SMALLINT, hi BIGINT NOT NULL DEFAULT -7, PRIMARY KEY (HI DESC, lo))');
    $key = $value->primaryKey;
    $direct = new SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKey([
        new SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKeyColumn(1, true),
        new SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKeyColumn(0),
    ]);

    return [
        $key::class, $key->terms[0]::class,
        array_map(static fn ($term): array => [$term->slot, $term->descending], $key->terms),
        $direct == $key, $value->columns[0]->nullable, $value->columns[0]->default, $value->toString(),
    ];
}

function observePostgresSignedDefault(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $value = $semantics->analyze('CREATE TABLE public.signed_default_decl (minimum SMALLINT DEFAULT -9223372036854775808, '
        . 'source_width BIGINT DEFAULT CAST(7 AS pg_catalog.int8), rendered pg_catalog.text DEFAULT 9223372036854775807)');

    return [array_map(static fn ($column): array => [
        $column->storage->value, $column->default::class, $column->default->decimal,
    ], $value->columns), $value->toString()];
}

function observePostgresBooleanDefault(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $value = $semantics->analyze("CREATE TABLE public.bool_decl (enabled pg_catalog.bool NOT NULL DEFAULT ' YE ', "
        . "disabled BOOLEAN DEFAULT CAST(' of ' AS pg_catalog.bool))");

    return [array_map(static fn ($column): array => [
        $column->storage::class, $column->storage->value, $column->nullable, $column->default::class, $column->default->value,
    ], $value->columns), $value->toString()];
}

function observePostgresBooleanFields(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.flags (id integer, enabled pg_catalog.bool)']);
    $value = $semantics->analyze('SELECT enabled AS first, f.enabled AS second, id FROM public.flags f WHERE f.enabled IS UNKNOWN', $context);
    $read = $value->columns[0]->expression;
    $direct = new SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference(0);
    $other = $value->columns[1]->expression;
    $predicate = $value->where;

    return [$read::class, $read == $direct, $other == $read, $predicate->operand == $read, $predicate->operator->value,
        [$value->source->columnKind(0)?->value, $value->source->columnKind(1)?->value], $value->toString()];
}

function observePostgresBooleanReturning(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.flags (id integer, enabled BOOLEAN)']);
    $value = $semantics->analyze('DELETE FROM public.flags WHERE enabled RETURNING enabled AS flag, id', $context);
    $field = $value->returning[0];
    $direct = new SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturnedColumn(
        new SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference(0),
        new SqlSemantics\Platform\PostgreSql\Ir\Identifier('flag'),
    );

    return [$field->reference::class, $field == $direct, $field->reference == $value->where,
        [$field->name->value, $value->returning[1]->name->value], $value->toString()];
}

function observePostgresBooleanWrites(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.flags (id integer, enabled BOOLEAN, fallback BOOLEAN)']);
    $literal = $semantics->analyze('INSERT INTO public.flags (enabled,fallback) VALUES (TRUE,NULL)', $context);
    $query = $semantics->analyze(
        'INSERT INTO public.flags (fallback) SELECT DISTINCT CAST(NULL AS pg_catalog.bool) AS typed',
        $context,
    );
    $update = $semantics->analyze('UPDATE public.flags SET enabled=fallback, fallback=enabled', $context);

    return [
        array_map(static fn ($slot): array => [$slot->slot, $slot->kind->value], $literal->target->written),
        $literal->rows[0]->values[0]->value->value, $literal->rows[0]->values[1]->value, $literal->toString(),
        $query->query->columns[0]->expression->target->value, $query->query->distinct, $query->toString(),
        array_map(static fn ($assignment): array => [$assignment->target::class, $assignment->target->slot, $assignment->value->slot], $update->assignments),
        $update->toString(),
    ];
}

function observePostgresBooleanInput(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.flags (enabled BOOLEAN)']);
    $ordinary = $semantics->analyze("INSERT INTO public.flags (enabled) SELECT ' Of ' AS value", $context);
    $distinct = $semantics->analyze(
        "INSERT INTO public.flags (enabled) SELECT DISTINCT CAST(' YE ' AS pg_catalog.bool) AS value",
        $context,
    );
    $negative = null;
    try {
        $semantics->analyze("INSERT INTO public.flags (enabled) SELECT DISTINCT ' YE ' AS value", $context);
    } catch (SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction $failure) {
        $negative = $failure->getMessage();
    }
    $literal = $ordinary->query->columns[0]->expression;
    $cast = $distinct->query->columns[0]->expression;

    return [$literal::class, $literal->value, $cast->target->value, $cast->operand->value, $negative,
        $ordinary->toString(), $distinct->toString()];
}

function observePostgresInt8Field(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.bounds_read (lo bigint, enabled boolean)']);
    $value = $semantics->analyze(
        'SELECT ((lo)) AS grouped, b.lo AS resolved, CAST(b.lo AS integer) AS narrow FROM public.bounds_read b',
        $context,
    );
    $first = $value->columns[0]->expression;
    $second = $value->columns[1]->expression;
    $narrowed = $value->columns[2]->expression;

    return [$first::class, $first->slot, $first->occurrence, $second == $first, $narrowed->operand == $first, $narrowed->target->value,
        $value->source->columnKind($first->slot)?->value, $value->toString()];
}

function observePostgresOutputOrder(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.bounds_read (id integer, lo bigint)']);
    $value = $semantics->analyze(
        'SELECT lo AS id, id AS signed FROM public.bounds_read ORDER BY (id) DESC NULLS LAST, signed LIMIT 2 OFFSET 1',
        $context,
    );

    return [array_map(static fn ($key): array => [$key->target, $key->descending, $key->nullsFirst], $value->orderBy),
        [$value->limit->count, $value->limit->offset], $value->toString()];
}


function observePostgresGroupedBounds(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.group_bounds (id integer)']);
    $group = $semantics->analyze(
        'SELECT id AS owner, count(*) AS n FROM public.group_bounds GROUP BY id ORDER BY n DESC NULLS LAST, owner LIMIT 2 OFFSET 1',
        $context,
    );

    return [$group::class, array_map(static fn ($key): array => [$key->target, $key->descending, $key->nullsFirst], $group->orderBy),
        [$group->limit->count, $group->limit->offset], $group->toString()];
}

function observePostgresExternalSourceKinds(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context([
        'CREATE VIEW public.active_rows AS SELECT 1::bigint AS id',
        'CREATE MATERIALIZED VIEW public.saved_rows AS SELECT 2::bigint AS id',
    ]);
    $view = $semantics->analyze('SELECT v.id FROM public.active_rows v', $context);
    $stored = $semantics->analyze('SELECT id FROM ONLY public.saved_rows', $context);
    $rejection = null;
    try {
        new SqlSemantics\Platform\PostgreSql\Ir\Mutation\Delete($view->source);
    } catch (SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction $error) {
        $rejection = $error->getMessage();
    }

    return [$view->source->kind->value, $stored->source->kind->value, $stored->source->only,
        $stored->source->columnKind(0)->value, $view->toString(), $stored->toString(), $rejection];
}

function observePostgresHaving(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.having_input (id integer, amount integer)']);
    $query = $semantics->analyze(
        'SELECT id AS key FROM public.having_input GROUP BY id HAVING SUM(amount) > 2147483649 ORDER BY key',
        $context,
    );
    $having = $query->having;

    return [$having::class, $having->left::class, $having->left->operator->value, $having->left->argument->slot,
        $having->operator->value, $having->right::class, $having->right->decimal, $query->toString()];
}

function observePostgresSignedRangeWidths(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.range_fields (amount bigint)']);
    $query = $semantics->analyze(
        'SELECT amount IS NOT DISTINCT FROM CAST(NULL AS bigint) AS exact_null FROM public.range_fields',
        $context,
    );
    $predicate = $query->columns[0]->expression;

    return [$predicate::class, $predicate->left::class, $predicate->left->slot, $predicate->right::class,
        $predicate->negated, $query->toString()];
}

function observePostgresWithProjection(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $query = $semantics->analyze(
        'WITH a AS (SELECT 42 AS n), b AS MATERIALIZED (SELECT n FROM a) SELECT n AS answer FROM b',
    );

    return [$query->definitions[1]->materialization->value, $query->definitions[1]->body->definition,
        $query->body->definition, $query->body->columns[0]->slot, $query->toString()];
}

function observePostgresSignedComparison(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.bounds_read (id integer, lo bigint)']);
    $query = $semantics->analyze('SELECT ((lo)) > id AS result FROM public.bounds_read WHERE id < lo', $context);
    $expression = $query->columns[0]->expression;

    return [$expression::class, $expression->left::class, $expression->left->slot,
        $expression->right::class, $expression->right->slot, $query->where->operator->value, $query->toString()];
}

function observePostgresSignedWrites(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.signed_bounds (id integer, wide bigint)']);
    $insert = $semantics->analyze(
        'INSERT INTO public.signed_bounds (wide,id) VALUES (-9223372036854775808,7) RETURNING wide AS low,id',
        $context,
    );
    $update = $semantics->analyze('UPDATE public.signed_bounds SET wide=id,id=wide RETURNING wide', $context);
    $cell = $insert->rows[0]->values[0]->value;
    $field = $insert->returning[0]->reference;

    return [$cell::class, $cell->decimal, $insert->target->written[0]->kind->value,
        $field::class, $field->slot, $field->occurrence, $insert->toString(),
        $update->assignments[0]->target::class, $update->assignments[1]->value::class, $update->toString()];
}

function observePostgresSourceOrder(SqlSemantics\Platform\PostgreSql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE public.bounds_read (id integer, lo bigint)']);
    $input = $semantics->analyze(
        'SELECT a.id AS lo FROM public.bounds_read a ORDER BY a.lo DESC NULLS LAST, 1 LIMIT 2 OFFSET 1',
        $context,
    );
    $distinct = $semantics->analyze(
        'SELECT DISTINCT a.lo AS value, a.id FROM public.bounds_read a ORDER BY a.lo DESC, a.id',
        $context,
    );
    $reference = $input->orderBy[0]->target;

    return [$reference::class, $reference->slot, $reference->occurrence, count($input->columns), $input->toString(),
        array_map(static fn ($key) => $key->target, $distinct->orderBy), $distinct->toString()];
}

foreach (array_keys($roles) as $name) {
    $role = new ReflectionClass($name);
    $path = $role->getFileName();
    if ($path === false) {
        throw new RuntimeException('An installed role has no source file.');
    }
    echo json_encode([
        'probe' => 'role',
        'name' => $role->getName(),
        'kind' => $role->isInterface() ? 'interface' : ($role->isEnum() ? 'enum' : 'class'),
        'final' => $role->isFinal(),
        'enum' => $role->isEnum() ? observeEnum($name) : null,
        'path' => $path,
        'sha256' => hash_file('sha256', $path),
    ], JSON_THROW_ON_ERROR) . "\n";
}

foreach (['MySql' => 'app', 'PostgreSql' => 'public', 'Sqlite' => 'main'] as $platform => $schema) {
    $namespace = 'SqlSemantics\\Platform\\' . $platform . '\\';
    $analyzer = $namespace . 'Semantics';
    $semantics = new $analyzer();
    if (array_intersect(['inspect', 'diagnose', 'ir'], get_class_methods($semantics)) !== []
        || interface_exists('SqlSemantics\\Query') || class_exists($namespace . 'Model\\Statement\\Operation')
        || class_exists('SqlSemantics\\Testing\\' . $platform . '\\Inspection')) {
        throw new RuntimeException('The installed package exposes an obsolete publication path.');
    }

    $table = $schema . '.accounts';
    $declarations = ["CREATE TABLE $table (id INTEGER)"];
    if ($platform === 'Sqlite') {
        $declarations[] = "CREATE VIEW main.removable AS SELECT id FROM $table";
    }
    $context = $semantics->context($declarations);
    $reference = $semantics->analyze("SELECT id, accounts.id FROM $table", $context);
    $occurrence = $platform === 'Sqlite' ? 'source' : 'occurrence';
    foreach ($reference->columns as $column) {
        $expression = $column->expression;
        if ([$expression::class, $expression->slot ?? null, $expression->$occurrence ?? null] !== [$namespace . 'Ir\\ColumnReference', 0, 0]) {
            throw new RuntimeException('The installed analyzer resolved different slots for equivalent references.');
        }
    }
    if ($platform !== 'Sqlite') {
        $parameterSql = $platform === 'MySql' ? 'SELECT CAST(? AS SIGNED) AS value' : 'SELECT CAST($1 AS integer) AS value';
        $value = $semantics->analyze($parameterSql)->columns[0]->expression;
        if ($value::class !== $namespace . 'Ir\\Binding\\Parameter' || $value->position !== 1) {
            throw new RuntimeException('The installed analyzer lost the projected parameter position.');
        }
    }
    $queries = [
        'Select' => 'SELECT 42 AS answer',
        'Rows\\Compound' => 'SELECT 1 AS a UNION ALL SELECT 2 AS b',
        'Mutation\\Delete' => "DELETE FROM $table WHERE id < 2",
        'Mutation\\Insert' => "INSERT INTO $table (id) VALUES (2)",
        'Mutation\\Update' => "UPDATE $table SET id = 2",
        'Schema\\CreateTable' => "CREATE TABLE $schema.other (id INTEGER)",
        'Schema\\DropTable' => "DROP TABLE IF EXISTS $table",
        'Transaction\\Transaction' => 'ROLLBACK TO SAVEPOINT bookmark',
    ];
    $queries['Aggregation\\GroupedSelect'] = "SELECT id, COUNT(*) AS entries FROM $table GROUP BY id";
    if ($platform === 'Sqlite') {
        $queries['Rows\\ValuesQuery'] = 'VALUES (1), (2)';
        $queries['Schema\\DropView'] = 'DROP VIEW IF EXISTS main.removable';
        $queries['Schema\\CreateTable'] = 'CREATE TABLE other (id INTEGER PRIMARY KEY, code TEXT COLLATE NOCASE UNIQUE CONSTRAINT "ref id" CHECK(id), UNIQUE(code COLLATE BINARY DESC) ON CONFLICT IGNORE)';
        $queries['Schema\\Query\\CreateTableAs'] = 'CREATE TABLE made AS SELECT CAST(9 AS INTEGER) AS result';
        $queries['Schema\\View\\CreateView'] = 'CREATE VIEW view_result("true") AS SELECT id FROM accounts';
        $queries['Schema\\Alter\\RenameTable'] = "ALTER TABLE $table RENAME TO renamed";
        $queries['Schema\\Alter\\RenameColumn'] = "ALTER TABLE $table RENAME COLUMN ID TO renamed";
    } else {
        $queries['Mutation\\InsertQuery'] = "INSERT INTO $table (id) SELECT 7 AS value";
        $queries['Rows\\LocalProjection'] = 'SELECT q.value FROM (SELECT 42 AS value) AS q';
        $queries['Rows\\RecursiveSeries'] = 'WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT t.n + 1 FROM t WHERE t.n < 3) SELECT n AS value FROM t';
        $queries['Select'] = 'SELECT 42 AS answer WHERE (NOT NULL) IS NULL';
        $predicate = $semantics->analyze($queries['Select'])->where;
        if ($predicate::class !== $namespace . 'Ir\\Predicate\\NullTest'
            || $predicate->operand::class !== $namespace . 'Ir\\Predicate\\LogicalPredicate'
            || $predicate->operand->operator->value !== 'NOT') {
            throw new RuntimeException('The installed analyzer lost the composed predicate.');
        }
    }

    if ($platform === 'MySql') {
        $queries['Select'] = 'SELECT CAST(42 AS SIGNED) AS answer, CAST(-1 AS UNSIGNED) AS bound WHERE (NOT NULL) IS NULL';
    }
    if ($platform === 'PostgreSql') {
        $queries['Rows\\With\\WithProjection'] = 'WITH a AS (SELECT 42 AS n), b AS (SELECT n FROM a) SELECT n FROM b';
    }
    if ($platform === 'PostgreSql') {
        $queries['Select'] = 'SELECT CAST(CAST(42 AS bool) AS integer) AS answer, CAST(CAST(2147483648 AS BIGINT) AS INTEGER) AS narrow, -9223372036854775808 AS exact WHERE (NOT NULL) IS NULL';
    }
    foreach ($queries as $role => $sql) {
        $result = $semantics->analyze($sql, $context);
        if (!$result instanceof SqlSemantics\SemanticsIR || $result::class !== $namespace . 'Ir\\' . $role) {
            throw new RuntimeException('The installed analyzer published an unexpected role.');
        }
        $details = [];
        if ($platform === 'PostgreSql' && $role === 'Mutation\\Delete') {
            $details['booleanReturning'] = observePostgresBooleanReturning($semantics);
            $details['booleanWrites'] = observePostgresBooleanWrites($semantics);
            $details['booleanInput'] = observePostgresBooleanInput($semantics);
            $details['signedWrites'] = observePostgresSignedWrites($semantics);
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\CreateTable') {
            $details['postgresSignedDefault'] = observePostgresSignedDefault($semantics);
            $details['postgresBooleanDefault'] = observePostgresBooleanDefault($semantics);
        }
        if ($platform === 'MySql' && $role === 'Schema\\CreateTable') {
            $details['mysqlVarcharDeclaration'] = observeMysqlVarcharDeclaration($semantics);
            $details['mysqlSignedDeclaration'] = observeMysqlSignedDeclaration($semantics);
            $details['mysqlPrimaryKey'] = observeMysqlPrimaryKey($semantics);
        }
        if ($platform === 'MySql' && $role === 'Select') {
            $value = $result->columns[0]->expression;
            if ($value::class !== $namespace . 'Ir\\Expression\\SignedCast'
                || $value->operand::class !== $namespace . 'Ir\\IntegerLiteral') {
                throw new RuntimeException('The installed projection lost the signed conversion.');
            }
            $details['signedCast'] = $value->operand->decimal;
            $unsigned = $result->columns[1]->expression;
            if ($unsigned::class !== $namespace . 'Ir\\Expression\\UnsignedCast'
                || $unsigned->operand::class !== $namespace . 'Ir\\IntegerLiteral') {
                throw new RuntimeException('The installed projection lost the unsigned conversion.');
            }
            $details['unsignedCast'] = $unsigned->operand->decimal;
            $text = $semantics->analyze("SELECT 'it''s \\0' '𐐷' AS `sample`");
            $literal = $text->columns[0]->expression;
            if ($literal::class !== $namespace . 'Ir\\Literal\\TextLiteral') {
                throw new RuntimeException('The installed projection lost the decoded MySQL character role.');
            }
            $legacyRelease = SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::MySql5651;
            $details['mysqlTextLiteral'] = [$literal->value, $text->columns[0]->name->value, $text->toString(),
                $legacyRelease->textCollation(), $text->release->textCollation()];
            $details['mysqlVarcharPublication'] = observeMysqlVarchar($semantics);
        }
        if ($platform === 'PostgreSql' && $role === 'Select') {
            $castRole = $namespace . 'Ir\\Expression\\BuiltinCast';
            $value = $result->columns[0]->expression;
            if ($value::class !== $castRole || $value->operand::class !== $castRole
                || $value->operand->operand::class !== $namespace . 'Ir\\IntegerLiteral') {
                throw new RuntimeException('The installed projection lost an explicit scalar conversion.');
            }
            $details['nestedCast'] = [$value->target->value, $value->operand->target->value, $value->operand->operand->decimal];
            $wide = $result->columns[1]->expression;
            $exact = $result->columns[2]->expression;
            $wideRole = $namespace . 'Ir\\Expression\\WideIntegerLiteral';
            if ($wide::class !== $castRole || $wide->operand::class !== $castRole
                || $wide->operand->operand::class !== $wideRole || $exact::class !== $wideRole) {
                throw new RuntimeException('The installed projection lost a bigint operand or result.');
            }
            $details['wideCast'] = [$wide->target->value, $wide->operand->target->value, $wide->operand->operand->decimal, $exact->decimal];
            $bindings = $semantics->analyze('SELECT $2::integer AS a0, $1::bigint AS a1, $1::integer AS a2', $context)->columns;
            $int4 = $bindings[0]->expression;
            $int8 = $bindings[1]->expression;
            $narrow = $bindings[2]->expression;
            $parameterRole = $namespace . 'Ir\\Binding\\Parameter';
            if ($int4::class !== $parameterRole || $int8::class !== $parameterRole || $narrow::class !== $castRole
                || $narrow->operand::class !== $parameterRole) {
                throw new RuntimeException('The installed projection lost the distinction between a binding and conversion.');
            }
            $details['typedParameters'] = [
                [$int4->position, $int4->input->value], [$int8->position, $int8->input->value],
                [$narrow->operand->position, $narrow->operand->input->value, $narrow->target->value],
            ];
            $text = $semantics->analyze("SELECT 'it''s \\値' AS sample");
            $literal = $text->columns[0]->expression;
            if ($literal::class !== $namespace . 'Ir\\Expression\\TextLiteral') {
                throw new RuntimeException('The installed projection lost the resolved text literal.');
            }
            $details['textLiteral'] = [$literal->value, $text->columns[0]->name->value, $text->toString()];
            $preparedText = $semantics->analyze('SELECT $1::pg_catalog.text AS a0');
            $marker = $preparedText->columns[0]->expression;
            if ($marker::class !== $parameterRole) {
                throw new RuntimeException('The installed projection lost the resolved text binding.');
            }
            $details['textMarker'] = [$marker->position, $marker->input->value, $preparedText->toString()];
            $typedContext = $semantics->context(['CREATE TABLE public.typed_table (id integer, label text)', 'CREATE TEMP TABLE text (part integer)']);
            $typed = $semantics->analyze('SELECT label, t.label, t.id FROM public.typed_table t', $typedContext);
            $columns = [];
            foreach ($typed->columns as $column) {
                $field = $column->expression;
                $columns[] = [substr($field::class, strlen($namespace . 'Ir\\')), $field->slot, $field->occurrence, $column->name->value];
            }
            $details['booleanFields'] = observePostgresBooleanFields($semantics);
            $details['int8Field'] = observePostgresInt8Field($semantics);
            $details['outputOrder'] = observePostgresOutputOrder($semantics);
            $details['sourceOrder'] = observePostgresSourceOrder($semantics);
            $details['signedComparison'] = observePostgresSignedComparison($semantics);
            $details['groupedBounds'] = observePostgresGroupedBounds($semantics);
            $details['externalSourceKinds'] = observePostgresExternalSourceKinds($semantics);
            $details['groupedHaving'] = observePostgresHaving($semantics);
            $details['signedRangeWidths'] = observePostgresSignedRangeWidths($semantics);
            $details['withProjection'] = observePostgresWithProjection($semantics);
            $details['typedColumns'] = [
                array_map(static fn ($kind) => $kind->value, $typed->source->columnKinds),
                $columns,
                $typed->toString(),
            ];
            $joinedText = $semantics->analyze(
                'SELECT q.label FROM public.typed_table a CROSS JOIN (SELECT label, id FROM public.typed_table) q',
                $typedContext,
            );
            $export = $joinedText->source->right;
            $reference = $joinedText->columns[0]->expression;
            if ($export::class !== $namespace . 'Ir\\From\\DerivedSource'
                || $reference::class !== $namespace . 'Ir\\From\\TextColumnReference') {
                throw new RuntimeException('The installed analyzer lost the native derived text slot.');
            }
            $details['derivedText'] = [
                $export->columnKind(0)?->value, $export->columnKind(1)?->value,
                $reference->slot, $reference->occurrence, $joinedText->columns[0]->name->value,
                str_starts_with($joinedText->toString(), 'SELECT "__ztd_source_1"."__ztd_c0" AS "label" FROM '),
            ];
            $returning = $semantics->analyze(
                'DELETE FROM public.typed_table WHERE FALSE RETURNING label AS removed, id',
                $typedContext,
            );
            $details['typedReturning'] = array_map(
                static fn ($field): array => [
                    substr($field->reference::class, strlen($namespace . 'Ir\\')),
                    $field->reference->slot,
                    $field->name->value,
                ],
                $returning->returning,
            );
            $writeContext = $semantics->context(['CREATE TABLE public.text_updates (id integer, label text, note text)']);
            $written = $semantics->analyze(
                'UPDATE public.text_updates SET label = note, note = label RETURNING label, note',
                $writeContext,
            );
            $details['typedUpdate'] = array_map(
                static fn ($assignment): array => [
                    substr($assignment->target::class, strlen($namespace . 'Ir\\')),
                    $assignment->target->slot,
                    $assignment->value->slot,
                ],
                $written->assignments,
            );
            $textInsert = $semantics->analyze(
                "INSERT INTO public.text_updates (label, id) VALUES ('', 4) RETURNING label, id",
                $writeContext,
            );
            $details['typedInsert'] = [
                array_map(static fn ($slot): array => [$slot->kind->value, $slot->slot], $textInsert->target->written),
                $textInsert->rows[0]->values[0]->value->value,
                $textInsert->rows[0]->values[1]->value->decimal,
            ];
            $selectInsert = $semantics->analyze(
                'INSERT INTO public.text_updates (label, id) SELECT DISTINCT NULL AS label, 4 AS id RETURNING label',
                $writeContext,
            );
            $details['typedInsertQuery'] = [
                array_map(static fn ($slot): string => $slot->kind->value, $selectInsert->target->written),
                $selectInsert->query->distinct,
                str_contains($selectInsert->toString(), 'SELECT DISTINCT CAST(NULL AS pg_catalog.text) AS "label"'),
            ];
            $details['nullableKinds'] = [
                [$typed->source->columnKind(0)?->value, $typed->source->columnKind(1024)?->value],
                [$export->columnKind(0)?->value, $export->columnKind(1024)?->value],
                [$textInsert->target->columnKind(0)?->value, $textInsert->target->columnKind(1024)?->value],
            ];
        }
        if ($platform === 'Sqlite' && $role === 'Select') {
            $joined = $semantics->analyze('WITH source(id) AS (VALUES (?02), (3)) SELECT a.id, b.id FROM source a LEFT JOIN source b ON a.id=b.id');
            if ($joined->source::class !== $namespace . 'Ir\\Rows\\LocalSource'
                || $joined->columns[0]->expression::class !== $namespace . 'Ir\\ColumnReference'
                || $joined->columns[1]->expression::class !== $namespace . 'Ir\\ColumnReference') {
                throw new RuntimeException('Installed SQLite join lost its local definition or references.');
            }
            $details['joinedLocal'] = [
                $joined->source === $joined->joins[0]->source, $joined->columns[0]->expression->source,
                $joined->columns[1]->expression->source, substr_count($joined->toString(), '?02'),
            ];
            $selection = $semantics->analyze('SELECT IFNULL(NULL, ?007) AS chosen');
            $expression = $selection->columns[0]->expression;
            if ($expression::class !== $namespace . 'Ir\\Expression\\Nulls\\Coalesce'
                || $expression->arguments[0]::class !== $namespace . 'Ir\\Expression\\Nulls\\CoalesceArgument'
                || $expression->arguments[0]->expression::class !== $namespace . 'Ir\\NullLiteral'
                || $expression->arguments[1]->expression::class !== $namespace . 'Ir\\Parameter') {
                throw new RuntimeException('The installed first non-NULL scalar lost its ordered operand roles.');
            }
            $details['coalesce'] = [count($expression->arguments), $expression->arguments[1]->expression->label, $selection->toString()];
            $read = $semantics->analyze('SELECT * FROM main.removable', $context);
            if ($read->source === null || $read->source::class !== $namespace . 'Ir\\TableSource'
                || $read->source->kind::class !== $namespace . 'Ir\\From\\ExternalRelationKind') {
                throw new RuntimeException('Installed SQLite read lost its resolved view occurrence.');
            }
            $details['viewInput'] = [$read->source->schema->value, $read->source->table->value, $read->source->kind->value,
                array_column($read->source->columns, 'value'), $read->columns[0]->expression->slot];
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\CreateTable') {
            $textTable = $semantics->analyze("CREATE TABLE public.text_decl (label pg_catalog.text NOT NULL DEFAULT 'quote''s')");
            $column = $textTable->columns[0];
            $details['postgresTextStorage'] = [$column->storage->value, $column->nullable, $column->default->value, $textTable->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\CreateTable') {
            $details['keyLayout'] = array_map(static fn ($key): array => [
                $key instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey ? 'primary' : 'unique',
                $key->inline, $key->terms[0]->slot, $key->terms[0]->descending, $key->terms[0]->collation->value,
                $key->conflict?->value,
            ], $result->keys);
            $implicit = $semantics->analyze('CREATE TABLE bounded (a INTEGER CHECK (a ==00))', $context)->checks[0];
            $presentation = $implicit->label;
            $comparison = $implicit->condition;
            if ($presentation::class !== $namespace . 'Ir\\Schema\\Predicate\\ImplicitCheckLabel'
                || $comparison::class !== $namespace . 'Ir\\Predicate\\Comparison'
                || $comparison->left::class !== $namespace . 'Ir\\ColumnReference') {
                throw new RuntimeException('The installed analyzer lost the implicit CHECK role.');
            }
            $details['implicitCheck'] = [$presentation->columnName->value, $presentation->token->value, $presentation->quoted,
                $presentation->beforeOperator, $presentation->afterOperator, $presentation->leadingZeroes,
                $comparison->operator->value, $comparison->left->slot];
            $details['namedChecks'] = array_map(static function ($check) use ($namespace): array {
                if ($check::class !== $namespace . 'Ir\\Schema\\Predicate\\CheckConstraint'
                    || $check->condition::class !== $namespace . 'Ir\\ColumnReference' || $check->condition->source !== 0) {
                    throw new RuntimeException('The installed table condition lost its constraint or reference role.');
                }
                return [$check->label->value, $check->column, $check->condition->slot];
            }, $result->checks);
            $folded = $semantics->analyze('CREATE TABLE folded (code INTEGER, CONSTRAINT chk CHECK ((SELECT ?07 + ?1 + ?007) NOT IN ()))')->checks[0]->condition;
            if ($folded::class !== $namespace . 'Ir\\Predicate\\FoldedMembership' || !$folded->matches) {
                throw new RuntimeException('The installed declaration lost the folded empty membership.');
            }
            $details['foldedCheckBindings'] = array_column($folded->bindings, 'label');
            $generated = $semantics->analyze('CREATE TABLE generation (recorded INTEGER GENERATED ALWAYS AS (computed) STORED, source INTEGER, computed INTEGER AS (source))');
            $details['generatedColumns'] = [];
            foreach ($generated->columns as $slot => $field) {
                $rule = $field->generated;
                if ($rule === null) {
                    continue;
                }
                if ($rule::class !== $namespace . 'Ir\\Schema\\Generation\\ColumnGeneration'
                    || $rule->mode::class !== $namespace . 'Ir\\Schema\\Generation\\Storage'
                    || $rule->expression::class !== $namespace . 'Ir\\ColumnReference' || $rule->expression->source !== 0) {
                    throw new RuntimeException('Installed SQLite generation lost a resolved slot or storage role.');
                }
                $details['generatedColumns'][] = [$slot, $rule->mode->value, $rule->expression->slot];
            }
        }
        if ($role === 'Schema\\View\\CreateView') {
            $shadow = $semantics->context(['CREATE TABLE main.accounts (id INTEGER)', 'CREATE TEMP TABLE accounts (id TEXT)'], true);
            $view = $semantics->analyze($sql, $shadow);
            if ($view::class !== $namespace . 'Ir\\Schema\\View\\CreateView' || $view->query::class !== $namespace . 'Ir\\Select'
                || $view->query->source === null || $view->query->source::class !== $namespace . 'Ir\\TableSource') {
                throw new RuntimeException('Installed SQLite view lost the checked definition and external table occurrence.');
            }
            $details['createView'] = [$view->schema->value, $view->view->value, array_column($view->columns, 'value'),
                [$view->query->source->schema->value, $view->query->source->table->value], $view->ifNotExists];
            $groupContext = $semantics->context(['CREATE TABLE main.accounts (id INTEGER, code TEXT)'], true);
            $group = $semantics->analyze('CREATE VIEW view_group AS SELECT id, count(code) AS n FROM accounts GROUP BY id', $groupContext);
            if ($group::class !== $namespace . 'Ir\\Schema\\View\\CreateView'
                || $group->query::class !== $namespace . 'Ir\\Aggregation\\GroupedSelect'
                || $group->query->columns[1]->value::class !== $namespace . 'Ir\\Aggregation\\Count'
                || $group->query->columns[1]->value->argument === null) {
                throw new RuntimeException('Installed SQLite grouped view lost its checked aggregate and grouping roles.');
            }
            $details['groupedView'] = [array_column($group->columns, 'value'), $group->query->keys[0]->column->slot,
                $group->query->columns[1]->value->argument->slot, $group->query->keys[0]->collation->value, $group->profileId()];
            $merged = $semantics->analyze('CREATE VIEW view_set(selected) AS VALUES(4),(4) UNION ALL SELECT 2 AS item');
            if ($merged::class !== $namespace . 'Ir\\Schema\\View\\CreateView'
                || $merged->query::class !== $namespace . 'Ir\\Rows\\Compound'
                || $merged->query->first::class !== $namespace . 'Ir\\Rows\\ValuesQuery'
                || $merged->query->steps[0]->query::class !== $namespace . 'Ir\\Select') {
                throw new RuntimeException('Installed SQLite compound view lost its checked arm roles.');
            }
            $details['compoundView'] = [array_column($merged->columns, 'value'), $merged->query->steps[0]->operator->value, $merged->toString()];
            $localView = $semantics->analyze('CREATE VIEW view_local AS WITH pair(n) AS (VALUES(4),(9)) SELECT a.n AS first, b.n AS second FROM pair a CROSS JOIN pair b');
            if ($localView::class !== $namespace . 'Ir\\Schema\\View\\CreateView'
                || $localView->query::class !== $namespace . 'Ir\\Select'
                || $localView->query->source === null || $localView->query->source::class !== $namespace . 'Ir\\Rows\\LocalSource') {
                throw new RuntimeException('Installed SQLite view lost its local source role.');
            }
            $details['localView'] = [array_column($localView->columns, 'value'),
                $localView->query->source === $localView->query->joins[0]->source,
                $localView->query->columns[0]->expression->source, $localView->query->columns[1]->expression->source,
                substr_count($localView->toString(), 'WITH ')];
            $inputs = $semantics->context([
                'CREATE TABLE main.merge_left (Key TEXT COLLATE NOCASE)',
                'CREATE TABLE main.merge_right (key TEXT COLLATE BINARY)',
            ]);
            $full = $semantics->analyze('CREATE VIEW merged_result AS SELECT Key, a.Key AS original, b.key AS other FROM merge_left a FULL JOIN merge_right b USING (key)', $inputs);
            $right = $semantics->analyze('SELECT a.*, a.Key AS original FROM merge_left a RIGHT JOIN merge_right b USING (key)', $inputs);
            if ($full::class !== $namespace . 'Ir\\Schema\\View\\CreateView'
                || $full->query::class !== $namespace . 'Ir\\Select'
                || $full->query->columns[0]->expression::class !== $namespace . 'Ir\\Expression\\Nulls\\Coalesce'
                || $right::class !== $namespace . 'Ir\\Select') {
                throw new RuntimeException('Installed SQLite joins lost their resolved merge or result roles.');
            }
            $operands = [];
            foreach ($full->query->columns[0]->expression->arguments as $argument) {
                if ($argument->expression::class !== $namespace . 'Ir\\ColumnReference') {
                    throw new RuntimeException('Installed SQLite FULL merge operand is not a resolved column.');
                }
                $operands[] = [$argument->expression->source, $argument->expression->slot];
            }
            $details['mergedJoins'] = [
                $full->query->joins[0]->kind->value, array_column($full->columns, 'value'), $operands,
                $right->joins[0]->kind->value,
                array_map(static fn ($field) => [$field->name->value, $field->expression->source], $right->columns),
            ];
        }
        if ($role === 'Schema\\DropView') {
            $details['dropView'] = [$result->schema->value, $result->view->value, $result->ifExists];
        }
        if ($role === 'Schema\\Alter\\RenameTable') {
            $details['rename'] = [$result->schema->value, $result->table->value, $result->destination->value];
        }
        if ($role === 'Schema\\Alter\\RenameColumn') {
            $details['rename'] = [$result->schema->value, $result->table->value, $result->column->value,
                $result->destination->value, $result->quoteReferences];
        }
        if ($role === 'Schema\\Query\\CreateTableAs') {
            $columns = $result->columns();
            if (count($columns) !== 1 || $columns[0]::class !== $namespace . 'Ir\\Schema\\Query\\CreatedColumn') {
                throw new RuntimeException('The installed CREATE TABLE AS derives an unexpected column.');
            }
            $details['column'] = [$columns[0]->name->value, $columns[0]->type->value];
        }
        echo json_encode([
            'probe' => 'publication',
            'platform' => $platform,
            'role' => $role,
            'profile' => $result->profileId(),
            'path' => (new ReflectionClass($result))->getFileName(),
            'input' => $sql,
            'sql' => $result->toString(),
            ...$details,
        ], JSON_THROW_ON_ERROR) . "\n";
    }
}
