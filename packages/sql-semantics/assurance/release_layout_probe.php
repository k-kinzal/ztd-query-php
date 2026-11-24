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

function observeMysqlHaving(SqlSemantics\Platform\MySql\Semantics $semantics): array
{
    $context = $semantics->context(['CREATE TABLE app.having_rows (id INT, amount INT)']);
    $query = $semantics->analyze(
        'SELECT id, sum(amount) AS total FROM app.having_rows GROUP BY id HAVING total > 4294967292',
        $context,
    );
    $shadow = $semantics->analyze('SELECT count(*) AS id FROM app.having_rows GROUP BY id HAVING id > 1', $context);
    $condition = $query->having;

    return [$condition::class, $condition->left === $query->columns[1]->value, $condition->left->operator->value,
        $condition->right::class, $condition->right->decimal, $shadow->having->left::class, $shadow->having->left->slot,
        $query->toString(), $shadow->toString()];
}

function observeMysqlWithAvailability(string $sql, object $definition, object $body): array
{
    $rejections = [];
    $releases = [SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::MySql5651,
        SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::MySql5744];
    foreach ($releases as $release) {
        $direct = $analysis = null;
        try {
            new SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection([$definition], $body, $release);
        } catch (SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction $error) {
            $direct = $error->getMessage();
        }
        try {
            (new SqlSemantics\Platform\MySql\Semantics($release->value))->analyze($sql);
        } catch (SqlSemantics\Platform\MySql\Model\Diagnostic\AnalysisException $error) {
            $analysis = $error::class;
        }
        $rejections[] = [$release->value, $direct, $analysis];
    }

    return $rejections;
}

function observeMysqlExternalViews(SqlSemantics\Platform\MySql\Semantics $semantics): array
{
    $context = $semantics->context([
        'CREATE TABLE app.external_input (id INT, label VARCHAR(8) COLLATE utf8mb4_bin)',
        'CREATE ALGORITHM=TEMPTABLE VIEW app.external_rows (key_value, label_value) AS SELECT id, label FROM app.external_input',
    ]);
    $view = $semantics->analyze('SELECT v.key_value, label_value FROM app.external_rows AS v', $context);
    if (!$view instanceof SqlSemantics\Platform\MySql\Ir\Select
        || !$view->source instanceof SqlSemantics\Platform\MySql\Ir\TableSource
        || !$view->source->columnTypes[1] instanceof SqlSemantics\Platform\MySql\Ir\From\Varchar) {
        throw new RuntimeException('The installed MySQL view input lost its native source or field roles.');
    }
    $rejection = null;
    try {
        new SqlSemantics\Platform\MySql\Ir\Mutation\Delete($view->source);
    } catch (SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction $error) {
        $rejection = $error->getMessage();
    }
    $text = $view->source->columnTypes[1];

    return [$view->source->kind->value, $view->source->schema->value, $view->source->table->value,
        array_column($view->source->columns, 'value'), $text->length, $text->collation->value, $text->nullable,
        $view->toString(), $rejection];
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
    $target = new SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity(
        $location, SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::BaseTable
    );
    $direct = new SqlSemantics\Platform\MySql\Ir\Schema\CreateTable($target, [$n, $column], true, defaultCollation: $space);
    $type = $analyzed->columns[1]->storage;

    return [
        $analyzed::class, $analyzed->target->name->schema->value, $analyzed->target->name->table->value, $analyzed->ifNotExists,
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

function observeMysqlSessionCreateTable(SqlSemantics\Platform\MySql\Semantics $semantics): array
{
    $analyzed = $semantics->analyze('CREATE TEMPORARY TABLE IF NOT EXISTS app.scratch (n SMALLINT NOT NULL DEFAULT -7)');
    $direct = new SqlSemantics\Platform\MySql\Ir\Schema\CreateTable(
        new SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity(
            new SqlSemantics\Platform\MySql\Ir\Schema\TableName(
                new SqlSemantics\Platform\MySql\Ir\Identifier('app'), new SqlSemantics\Platform\MySql\Ir\Identifier('scratch')
            ),
            SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::TemporaryTable
        ),
        [new SqlSemantics\Platform\MySql\Ir\Schema\TableColumn(
            new SqlSemantics\Platform\MySql\Ir\Identifier('n'), SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Small,
            false, new SqlSemantics\Platform\MySql\Ir\IntegerLiteral('-7')
        )],
        true
    );

    return [$analyzed->target->name->schema->value, $analyzed->target->name->table->value,
        $analyzed->target->kind->value, $direct->target == $analyzed->target, $analyzed->toString(), $direct->toString()];
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
    if ($platform === 'PostgreSql') {
        $declarations[] = "CREATE VIEW public.removable AS SELECT id FROM $table";
        $declarations[] = "CREATE MATERIALIZED VIEW public.snapshot AS SELECT id FROM $table";
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
        $queries['Schema\\Alter\\AddColumn'] = "ALTER TABLE $table ADD COLUMN added TEXT COLLATE NOCASE DEFAULT 'value'";
        $queries['Schema\\Alter\\AddGeneratedColumn'] = "ALTER TABLE $table ADD total INTEGER AS (id + 7) STORED";
        $queries['Schema\\Alter\\DropColumn'] = 'ALTER TABLE removable DROP COLUMN OLD';
        $queries['Schema\\Index\\DropIndex'] = 'DROP INDEX tag';
        $queries['Maintenance\\Reindex'] = 'REINDEX names';
        $queries['Maintenance\\Analyze'] = 'ANALYZE names';
        $queries['Maintenance\\FlagPragma'] = "PRAGMA Temp.Writable_Schema('reset')";
        $queries['Maintenance\\Inventory\\InventoryPragma'] = 'PRAGMA Temp.function_list(+1)';
        $queries['Maintenance\\Metadata\\TableInspection'] = "PRAGMA table_xinfo(accounts)";
        $queries['Maintenance\\Metadata\\IndexInspection'] = 'PRAGMA index_xinfo(storage_table)';
        $queries['Maintenance\\Metadata\\ForeignKeyAudit'] = 'PRAGMA foreign_key_check(audit_child)';
        $queries['Maintenance\\Integrity\\IntegrityAudit'] = 'PRAGMA quick_check(integrity_child)';
        $queries['Maintenance\\Header\\HeaderPragma'] = 'PRAGMA metadata.user_version = -2147483648';
        $queries['Schema\\Trigger\\CreateTrigger'] = 'CREATE TRIGGER archive.audit_tr AFTER UPDATE OF payload,future ON watched WHEN OLD.payload IS NOT NULL BEGIN INSERT INTO journal(prior,current) VALUES(OLD.payload,NEW.payload); END';
        $queries['Maintenance\\Cache\\CacheSize'] = 'PRAGMA ARCHIVE.cache_size(-128)';
        $queries['Maintenance\\Cache\\DefaultCacheSize'] = 'PRAGMA ARCHIVE.default_cache_size(-2147483648)';
        $queries['Maintenance\\Cache\\CacheSpill'] = "PRAGMA ARCHIVE.cache_spill('+256')";
        $queries['Maintenance\\Reclaim\\AutoVacuum'] = 'PRAGMA ARCHIVE.auto_vacuum(INCREMENTAL)';
        $queries['Maintenance\\Reclaim\\IncrementalVacuum'] = 'PRAGMA ARCHIVE.incremental_vacuum(2)';
        $queries['Maintenance\\Page\\PagePragma'] = 'PRAGMA archive.max_page_count(4294967295)';
        $queries['Maintenance\\Vacuum\\Vacuum'] = 'VACUUM archive INTO ?002';
        $queries['Connection\\Attach'] = 'ATTACH ?002 AS reports KEY ?007';
        $queries['Connection\\Detach'] = "DETACH DATABASE 'ARChive'";
        $queries['Maintenance\\Metadata\\TableListing'] = "PRAGMA main.table_list('sqlite_master')";
        $queries['Schema\\Index\\CreateIndex'] = "CREATE UNIQUE INDEX IF NOT EXISTS indexed_tag ON indexed_rows('CODE' DESC) WHERE (?09+?1) NOT IN ()";
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
        $queries['Schema\\View\\CreateView'] = 'CREATE ALGORITHM=TEMPTABLE SQL SECURITY INVOKER VIEW app.answer (value) AS SELECT 42 AS original';
        $queries['Schema\\Like\\CreateTableLike'] = 'CREATE TEMPORARY TABLE IF NOT EXISTS app.copy_probe (LIKE app.accounts)';
        $queries['Schema\\Query\\CreateTableAs'] = 'CREATE TEMPORARY TABLE IF NOT EXISTS app.result_probe AS SELECT CAST(17 AS SIGNED) AS n, NULL AS absent';
        $queries['Schema\\View\\DropView'] = 'DROP VIEW IF EXISTS app.absent_view CASCADE';
        $queries['Schema\\Alter\\RenameTable'] = 'RENAME TABLE app.accounts TO app.staged, app.staged TO app.archived';
        $queries['Schema\\Truncate\\TruncateTable'] = 'TRUNCATE app.accounts';
        $queries['Select'] = 'SELECT CAST(42 AS SIGNED) AS answer, CAST(-1 AS UNSIGNED) AS bound WHERE (NOT NULL) IS NULL';
    }
    if ($platform === 'MySql' || $platform === 'PostgreSql') {
        $queries['Rows\\With\\WithProjection'] = 'WITH a AS (SELECT 42 AS n), b AS (SELECT n FROM a) SELECT n FROM b';
    }
    if ($platform === 'PostgreSql') {
        $queries['Schema\\View\\DropView'] = 'DROP MATERIALIZED VIEW snapshot CASCADE';
        $queries['Schema\\View\\CreateView'] = 'CREATE OR REPLACE VIEW ready_rows (n) WITH (security_invoker=FALSE) AS SELECT id FROM public.accounts WHERE id > 0 WITH LOCAL CHECK OPTION';
        $queries['Schema\\View\\CreateMaterializedView'] = 'CREATE MATERIALIZED VIEW materialized_probe (n) AS VALUES (6) WITH NO DATA';
        $queries['Schema\\Query\\CreateTableAs'] = 'CREATE UNLOGGED TABLE copy_probe (n) AS VALUES (6) WITH NO DATA';
        $queries['Schema\\Alter\\RenameTable'] = 'ALTER TABLE ONLY accounts RENAME TO archived';
        $queries['Schema\\Alter\\RenameColumn'] = 'ALTER TABLE IF EXISTS ONLY accounts RENAME id TO "new Id"';
        $queries['Schema\\Truncate\\TruncateTable'] = 'TRUNCATE TABLE ONLY accounts, accounts RESTART IDENTITY CASCADE';
        $queries['Schema\\View\\RefreshMaterializedView'] = 'REFRESH MATERIALIZED VIEW snapshot WITH NO DATA';
        $queries['Rows\\Values\\ValuesQuery'] = 'VALUES (FALSE, NULL), (TRUE, 4000000000)';
        $queries['Select'] = 'SELECT CAST(CAST(42 AS bool) AS integer) AS answer, CAST(CAST(2147483648 AS BIGINT) AS INTEGER) AS narrow, -9223372036854775808 AS exact WHERE (NOT NULL) IS NULL';
    }
    foreach ($queries as $role => $sql) {
        $activeContext = $context;
        if ($platform === 'Sqlite' && $role === 'Schema\\Alter\\DropColumn') {
            $activeContext = $semantics->context([
                'CREATE TABLE main.removable (old INTEGER UNIQUE, id INTEGER PRIMARY KEY)',
                'CREATE TEMP TABLE removable (id INTEGER PRIMARY KEY, keep INTEGER, old INTEGER, total AS (keep+1))',
            ]);
        }
        if ($platform === 'Sqlite' && $role === 'Connection\\Detach') {
            $configured = new SqlSemantics\Platform\Sqlite\Semantics(
                searchPath: new SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'archive'),
            );
            $activeContext = $configured->context([]);
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Vacuum\\Vacuum') {
            $configured = new SqlSemantics\Platform\Sqlite\Semantics(
                searchPath: new SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'archive'),
            );
            $activeContext = $configured->context([]);
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Trigger\\CreateTrigger') {
            $configured = new SqlSemantics\Platform\Sqlite\Semantics(
                searchPath: new SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'archive'),
            );
            $activeContext = $configured->context([
                'CREATE TABLE temp.watched (decoy)', 'CREATE TABLE main.watched (decoy)',
                'CREATE TABLE archive.watched (id,payload)', 'CREATE TABLE temp.journal (wrong)',
                'CREATE TABLE archive.journal (prior,current)',
            ]);
        }
        if ($platform === 'Sqlite' && str_starts_with($role, 'Maintenance\\Cache\\')) {
            $configured = new SqlSemantics\Platform\Sqlite\Semantics(
                searchPath: new SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'archive'),
            );
            $activeContext = $configured->context([]);
        }
        if ($platform === 'Sqlite' && in_array($role, ['Maintenance\\Reclaim\\AutoVacuum', 'Maintenance\\Reclaim\\IncrementalVacuum'], true)) {
            $configured = new SqlSemantics\Platform\Sqlite\Semantics(
                searchPath: new SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'archive'),
            );
            $activeContext = $configured->context([]);
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Page\\PagePragma') {
            $configured = new SqlSemantics\Platform\Sqlite\Semantics(
                searchPath: new SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'archive'),
            );
            $activeContext = $configured->context([]);
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Header\\HeaderPragma') {
            $configured = new SqlSemantics\Platform\Sqlite\Semantics(
                searchPath: new SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'metadata'),
            );
            $activeContext = $configured->context([]);
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Integrity\\IntegrityAudit') {
            $activeContext = $semantics->context([
                'CREATE TABLE main.integrity_child(id INT)',
                'CREATE TEMP TABLE integrity_child(id INT)',
            ]);
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Metadata\\ForeignKeyAudit') {
            $activeContext = $semantics->context([
                'CREATE TABLE main.audit_child(id INT REFERENCES parent(id))',
                'CREATE TEMP TABLE audit_child(id INT REFERENCES parent(id))',
            ]);
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Metadata\\IndexInspection') {
            $activeContext = $semantics->context([
                'CREATE TABLE main.storage_table(code INT, label TEXT, UNIQUE(label), PRIMARY KEY(code DESC)) WITHOUT ROWID',
            ]);
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Metadata\\TableInspection') {
            $activeContext = $semantics->context([...$declarations, 'CREATE TEMP TABLE accounts(code TEXT, doubled INT AS (3*2))']);
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Analyze') {
            $configured = new SqlSemantics\Platform\Sqlite\Semantics(
                searchPath: new SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', 'extension'),
            );
            $activeContext = $configured->context([
                'CREATE TABLE main.names (id INTEGER PRIMARY KEY)',
                'CREATE TABLE main.extension (id INTEGER PRIMARY KEY)',
                'CREATE TABLE temp.accounts (id INT PRIMARY KEY, code TEXT)',
                'CREATE INDEX temp.names ON accounts(code)',
                'CREATE VIEW main.stats_view AS SELECT id FROM main.names',
            ]);
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Reindex') {
            $activeContext = $semantics->context([
                'CREATE TABLE main.names (id INTEGER PRIMARY KEY)',
                'CREATE TABLE main.nocase (id INTEGER PRIMARY KEY)',
                'CREATE TABLE temp.accounts (id INT PRIMARY KEY, code TEXT)',
                'CREATE INDEX temp.names ON accounts(code)',
            ]);
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Index\\DropIndex') {
            $activeContext = $semantics->context([
                'CREATE TABLE main.indexed_rows (id INTEGER PRIMARY KEY)',
                'CREATE TABLE temp.local_rows (id INTEGER PRIMARY KEY)',
                'CREATE INDEX main.Tag ON indexed_rows(id)',
                'CREATE INDEX temp.tAg ON local_rows(id)',
            ]);
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Index\\CreateIndex') {
            $activeContext = $semantics->context([
                'CREATE TABLE main.indexed_rows (code TEXT, id INTEGER)',
                'CREATE TABLE temp.indexed_rows (id INTEGER PRIMARY KEY, code TEXT COLLATE NOCASE)',
            ]);
        }
        $result = $semantics->analyze($sql, $activeContext);
        if (!$result instanceof SqlSemantics\SemanticsIR || $result::class !== $namespace . 'Ir\\' . $role) {
            throw new RuntimeException('The installed analyzer published an unexpected role.');
        }
        $details = [];
        if ($platform === 'MySql' && $role === 'Schema\\View\\CreateView') {
            $legacy = new SqlSemantics\Platform\MySql\Semantics('mysql-5.6.51');
            $filtered = $legacy->analyze('CREATE VIEW app.filtered AS SELECT 42 AS value FROM DUAL WHERE FALSE');
            $details['createView'] = [$result->policy->value, $result->algorithm->value, $result->security->value,
                array_map(static fn ($field): string => $field->name->value, $result->columns), $filtered->toString()];
        }
        if ($platform === 'PostgreSql' && $role === 'Rows\\With\\WithProjection') {
            $table = $semantics->analyze('WITH q AS MATERIALIZED (VALUES (5,TRUE), (NULL,FALSE)) SELECT column2 AS flag,column1 AS id FROM q');
            $body = $table->definitions[0]->body;
            if ($body::class !== $namespace . 'Ir\\Rows\\Values\\ValuesQuery'
                || $table->body::class !== $namespace . 'Ir\\Rows\\With\\WithRead') {
                throw new RuntimeException('An installed VALUES relation lost its local body and positional reads.');
            }
            $details['withValues'] = [count($body->rows), $body->columnKind(0)->value, $body->columnKind(1)->value,
                $table->body->definition, array_column($table->body->columns, 'slot'), $table->toString()];
        }
        if ($platform === 'PostgreSql' && $role === 'Rows\\Compound') {
            $set = $semantics->analyze('VALUES (5),(5) EXCEPT ALL (SELECT 5 AS other UNION ALL VALUES (4000000000))');
            if ($set::class !== $namespace . 'Ir\\Rows\\Compound'
                || $set->left::class !== $namespace . 'Ir\\Rows\\Values\\ValuesQuery'
                || $set->right::class !== $namespace . 'Ir\\Rows\\Compound'
                || $set->right->right::class !== $namespace . 'Ir\\Rows\\Values\\ValuesQuery') {
                throw new RuntimeException('The installed set lost its native constant-table arms.');
            }
            $details['valuesCompound'] = [$set->operator->value, $set->all, $set->left->columnKind(0)->value,
                $set->right->right->columnKind(0)->value, $set->toString()];
        }
        if ($platform === 'PostgreSql' && $role === 'Rows\\Values\\ValuesQuery') {
            $details['valuesHeading'] = [$result->columnKind(0)->value, $result->columnKind(1)->value, count($result->rows)];
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\View\\CreateView') {
            $input = $result->query;
            if ($input::class !== $namespace . 'Ir\\Select' || $input->source::class !== $namespace . 'Ir\\TableSource'
                || $input->columns[0]->expression::class !== $namespace . 'Ir\\ColumnReference') {
                throw new RuntimeException('The installed view lost its resolved source.');
            }
            $details['postgresCreateView'] = [$result->name->schema->value, $result->name->view->value,
                array_column($result->columns, 'value'), $result->replace, $result->securityBarrier,
                $result->securityInvoker, $result->checkOption->value,
                $input->source->schema->value, $input->source->table->value, $input->columns[0]->expression->slot];
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\Query\\CreateTableAs') {
            if ($result->query::class !== $namespace . 'Ir\\Rows\\Values\\ValuesQuery') {
                throw new RuntimeException('The installed table declaration lost its native defining query.');
            }
            $details['postgresCreateTableAs'] = [$result->target->schema->value, $result->target->table->value,
                array_column($result->columns, 'value'), $result->query->columnKind(0)->value,
                $result->unlogged, $result->ifNotExists, $result->withData];
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\View\\CreateMaterializedView') {
            if ($result->query::class !== $namespace . 'Ir\\Rows\\Values\\ValuesQuery') {
                throw new RuntimeException('The installed materialized view lost its native constant-table definition.');
            }
            $details['postgresMaterializedView'] = [$result->name->schema->value, $result->name->view->value,
                array_column($result->columns, 'value'), $result->query->columnKind(0)->value,
                $result->ifNotExists, $result->withData];
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\View\\RefreshMaterializedView') {
            $details['postgresRefreshMaterializedView'] = [$result->name->schema->value, $result->name->view->value, $result->withData];
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\View\\DropView') {
            $ordinary = $semantics->analyze('DROP VIEW IF EXISTS public.removable RESTRICT', $context);
            if ($ordinary::class !== $namespace . 'Ir\\Schema\\View\\DropView') {
                throw new RuntimeException('The installed ordinary view removal lost its concrete kind.');
            }
            $details['dropView'] = [$result->kind->value, $result->targets[0]->schema->value, $result->targets[0]->view->value,
                $result->ifExists, $result->cascade, $ordinary->kind->value, $ordinary->toString()];
        }
        if ($platform === 'MySql' && $role === 'Schema\\View\\DropView') {
            $details['dropView'] = [$result->targets[0]->schema->value, $result->targets[0]->view->value, $result->ifExists];
        }
        if ($platform === 'MySql' && $role === 'Rows\With\WithProjection') {
            $predecessor = $result->definitions[1]->body;
            $body = $result->body;
            $details['withCoordinates'] = [$predecessor->definition, $predecessor->columns[0]->slot,
                $body->definition, $body->columns[0]->slot, $result->width()];
            $details['withAvailability'] = observeMysqlWithAvailability($sql, $result->definitions[0], $predecessor);
        }
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
            $details['mysqlSessionCreateTable'] = observeMysqlSessionCreateTable($semantics);
        }
        if ($platform === 'MySql' && $role === 'Schema\\Query\\CreateTableAs') {
            $direct = new SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs($result->target, $result->query, $result->ifNotExists);
            $first = $result->query->columns[0];
            $rejected = false;
            try {
                $duplicate = new SqlSemantics\Platform\MySql\Ir\Select([$first, $first]);
                new SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs($result->target, $duplicate);
            } catch (SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction $error) {
                $rejected = $error->getMessage() === 'CREATE TABLE AS cannot repeat a column name.';
            }
            $details['mysqlCreateTableAs'] = [$result->target->name->schema->value, $result->target->name->table->value,
                $result->target->kind->value, $result->ifNotExists, array_column(array_column($result->query->columns, 'name'), 'value'),
                $first->expression::class, $result->profileId(), $result->toString(), $direct->toString(), $rejected];
            $from = $semantics->analyze(
                'CREATE TABLE app.bound_probe AS SELECT id AS a, app.ctas_probe.id AS b FROM app.ctas_probe',
                $semantics->context(['CREATE TABLE app.ctas_probe (id INT)', 'CREATE TEMPORARY TABLE app.ctas_probe (id INT NOT NULL)'])
            );
            if (!$from instanceof SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs
                || !$from->query->source instanceof SqlSemantics\Platform\MySql\Ir\TableSource) {
                throw new RuntimeException('The installed table creation lost its resolved input.');
            }
            $slots = [];
            foreach ($from->query->columns as $column) {
                if (!$column->expression instanceof SqlSemantics\Platform\MySql\Ir\ColumnReference) {
                    throw new RuntimeException('The installed input lost its signed field role.');
                }
                $slots[] = [$column->name->value, $column->expression->occurrence, $column->expression->slot];
            }
            $details['mysqlCreateTableAsSource'] = [$from->query->source->schema->value, $from->query->source->table->value,
                $from->query->source->kind->value, $slots, $from->toString()];
            $large = $semantics->context(['CREATE TABLE app.text_source (v VARCHAR(8191) COLLATE utf8mb4_bin, z VARCHAR(0) COLLATE utf8mb4_bin)']);
            $edge = $semantics->analyze('CREATE TABLE app.text_copy AS SELECT v AS a, v AS b, z AS c, z AS d FROM app.text_source', $large);
            if (!$edge instanceof SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs
                || !$edge->query->source instanceof SqlSemantics\Platform\MySql\Ir\TableSource) {
                throw new RuntimeException('The installed table creation lost its resolved VARCHAR input.');
            }
            $widths = [];
            foreach ($edge->query->source->columnTypes as $field) {
                if (!$field instanceof SqlSemantics\Platform\MySql\Ir\From\Varchar) {
                    throw new RuntimeException('The installed input lost its VARCHAR storage.');
                }
                $widths[] = [$field->length, $field->collation->value];
            }
            $rejectedWidth = false;
            try {
                $semantics->analyze('CREATE TABLE app.text_copy AS SELECT v AS a, v AS b, z AS c, z AS d, z AS e FROM app.text_source', $large);
            } catch (SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction $error) {
                $rejectedWidth = $error->getMessage() === 'The projected logical table record cannot exceed 65535 bytes.';
            }
            $details['mysqlCreateTableAsVarchar'] = [$widths, $edge->toString(), $rejectedWidth];
            $declared = $semantics->analyze(
                'CREATE TABLE app.declared_copy (pre SMALLINT DEFAULT 7, N TINYINT NOT NULL) AS SELECT NULL AS n'
            );
            if (!$declared instanceof SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs) {
                throw new RuntimeException('The installed table creation lost its declared fields.');
            }
            [$first, $second] = $declared->declaredColumns;
            $alias = $second;
            $caller = [$first, &$alias];
            $owned = new SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs($declared->target, $declared->query, false, $caller);
            $alias = $first;
            $details['mysqlCreateTableAsDeclaration'] = [array_column(array_column($owned->declaredColumns, 'name'), 'value'),
                $declared->toString(), $owned->toString(), $owned->declaredColumns[1] === $second];
        }
        if ($platform === 'MySql' && $role === 'Schema\\Like\\CreateTableLike') {
            $direct = new SqlSemantics\Platform\MySql\Ir\Schema\Like\CreateTableLike($result->destination, $result->source, true);
            $rejected = false;
            try {
                new SqlSemantics\Platform\MySql\Ir\Schema\Like\CreateTableLike($result->destination, $result->destination);
            } catch (SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction $error) {
                $rejected = $error->getMessage() === 'CREATE TABLE LIKE requires distinct source and destination names.';
            }
            $details['mysqlCreateLike'] = [$result->destination->name->schema->value, $result->destination->name->table->value,
                $result->destination->kind->value, $result->source->name->schema->value, $result->source->name->table->value,
                $result->source->kind->value, $result->ifNotExists, $result->toString(), $direct->toString(), $rejected];
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
            $details['mysqlExternalViews'] = observeMysqlExternalViews($semantics);
            $details['mysqlHaving'] = observeMysqlHaving($semantics);
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
        if ($platform === 'Sqlite' && $role === 'Schema\\View\\CreateView') {
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
        if ($platform === 'MySql' && $role === 'Schema\\Alter\\RenameTable') {
            $details['mysqlRenameMoves'] = [];
            foreach ($result->moves as $move) {
                if ($move::class !== $namespace . 'Ir\\Schema\\Alter\\TableRename') {
                    throw new RuntimeException('The installed rename lost its checked move role.');
                }
                $details['mysqlRenameMoves'][] = array_map(static fn ($name): array => [$name->schema->value, $name->table->value],
                    [$move->from, $move->to, $move->origin]);
            }
        }
        if ($platform === 'MySql' && $role === 'Select') {
            $shadowing = $semantics->context(['CREATE TABLE app.accounts (id int)', 'CREATE TEMPORARY TABLE app.accounts (id int)']);
            $projection = $semantics->analyze('SELECT id, accounts.id FROM app.accounts', $shadowing);
            $insert = $semantics->analyze('INSERT INTO app.accounts (id) VALUES (3)', $shadowing);
            $rename = $semantics->analyze('RENAME TABLE app.accounts TO app.archived', $shadowing);
            $details['mysqlSessionTableSources'] = [$projection->source->schema->value, $projection->source->table->value, $projection->source->kind->value,
                $projection->columns[0]->expression->slot, $projection->columns[1]->expression->slot, $insert->target->kind->value];
            $move = $rename->moves[0];
            $details['mysqlSessionRename'] = [$move->origin->schema->value, $move->origin->table->value, $move->to->table->value];
        }
        if ($platform === 'MySql' && $role === 'Schema\\View\\DropView') {
            $viewNamespaces = $semantics->context([
                'CREATE VIEW app.shadowed_view AS SELECT 1 AS id',
                'CREATE TEMPORARY TABLE app.shadowed_view (id int)',
                'CREATE TEMPORARY TABLE app.session_only (id int)',
            ]);
            $shadowed = $semantics->analyze('DROP VIEW app.shadowed_view', $viewNamespaces);
            $absent = $semantics->analyze('DROP VIEW IF EXISTS app.session_only', $viewNamespaces);
            $details['mysqlShadowedViewRemoval'] = array_map(
                static fn ($drop): array => [$drop->targets[0]->schema->value, $drop->targets[0]->view->value, $drop->ifExists, $drop->toString()],
                [$shadowed, $absent]
            );
        }
        if ($platform === 'MySql' && $role === 'Schema\\DropTable') {
            $temporary = $semantics->context([
                'CREATE TABLE app.accounts (id int)', 'CREATE TEMPORARY TABLE app.accounts (id int)',
                'CREATE TABLE app.retained (id int)',
            ]);
            $mixed = $semantics->analyze('DROP TABLE app.accounts, app.retained', $temporary);
            $local = $semantics->analyze('DROP TEMPORARY TABLE IF EXISTS app.accounts, app.retained', $temporary);
            $details['mysqlSessionDrop'] = array_map(
                static fn ($drop): array => [$drop->temporaryOnly, $drop->ifExists,
                    array_map(static fn ($target): string => isset($target->kind) ? $target->kind->value : 'absent', $drop->targets),
                    $drop->toString()],
                [$result, $mixed, $local]
            );
        }
        if ($platform === 'MySql' && $role === 'Schema\\Truncate\\TruncateTable') {
            $temporary = $semantics->context(['CREATE TABLE app.accounts (id int)', 'CREATE TEMPORARY TABLE app.accounts (id int)']);
            $local = $semantics->analyze('TRUNCATE app.accounts', $temporary);
            $details['mysqlTruncate'] = [$result->target->name->schema->value, $result->target->name->table->value,
                $result->target->kind->value, $local->target->kind->value];
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\Truncate\\TruncateTable') {
            $details['postgresTruncate'] = [$result->restartIdentity, $result->cascade, []];
            foreach ($result->targets as $target) {
                if ($target::class !== $namespace . 'Ir\\Schema\\Truncate\\TruncateTarget') {
                    throw new RuntimeException('The installed truncation lost its checked target role.');
                }
                $details['postgresTruncate'][2][] = [$target->table->schema->value, $target->table->table->value, $target->only];
            }
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\Alter\\RenameTable') {
            $details['postgresRenameTable'] = [$result->target->schema->value, $result->target->table->value,
                $result->destination->value, $result->ifExists];
        }
        if ($platform === 'PostgreSql' && $role === 'Schema\\Alter\\RenameColumn') {
            $details['postgresRenameColumn'] = [$result->target->schema->value, $result->target->table->value,
                $result->column->value, $result->destination->value, $result->only, $result->ifExists];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Alter\\RenameTable') {
            $details['rename'] = [$result->schema->value, $result->table->value, $result->destination->value];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Alter\\RenameColumn') {
            $details['rename'] = [$result->schema->value, $result->table->value, $result->column->value,
                $result->destination->value, $result->quoteReferences];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Alter\\AddColumn') {
            $first = $result->priorHeading[0];
            $heading = $result->priorHeading;
            $heading[0] = &$first;
            $direct = new SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumn($result->schema, $result->table, $heading, $result->strict, $result->column);
            $first = $result->column->name;
            $refused = false;
            try {
                new SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumn($result->schema, $result->table, [$first], $result->strict, $result->column);
            } catch (SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction) {
                $refused = true;
            }
            $details['addColumn'] = [$result->schema->value, $result->table->value, array_column($direct->priorHeading, 'value'),
                $result->strict, $result->column->name->value, $result->column->type->value, $result->column->collation->value,
                $result->column->default->value, $refused, $direct->toString()];
            $alter = $semantics->analyze('ALTER TABLE main.accounts ADD total INTEGER DEFAULT (7 - (5 - 2))', $context);
            $create = $semantics->analyze('CREATE TABLE main.calculated (amount TEXT DEFAULT (2 + 3))');
            $altered = $alter->column->default;
            $declared = $create->columns[0]->default;
            if (!$altered instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\ColumnDefaultExpression
                || !$declared instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\ColumnDefaultExpression
                || !$altered->expression instanceof SqlSemantics\Platform\Sqlite\Ir\Arithmetic
                || !$declared->expression instanceof SqlSemantics\Platform\Sqlite\Ir\Arithmetic
                || !$altered->expression->right instanceof SqlSemantics\Platform\Sqlite\Ir\Arithmetic) {
                throw new RuntimeException('The installed computed defaults lost their operand roles.');
            }
            $details['computedDefaults'] = [$altered->expression->operator->value, $altered->expression->right->operator->value,
                $declared->expression->operator->value, $alter->toString(), $create->toString()];
            $checked = $semantics->analyze(
                'ALTER TABLE main.accounts ADD total INTEGER DEFAULT 7 CONSTRAINT "minimum" CHECK (total > id)',
                $context,
            );
            $firstCheck = $checked->checks[0];
            $checks = [&$firstCheck];
            $detached = new SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddColumn(
                $checked->schema, $checked->table, $checked->priorHeading, $checked->strict, $checked->column, $checks,
            );
            $firstCheck = new SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint(
                new SqlSemantics\Platform\Sqlite\Ir\Identifier('other'),
                new SqlSemantics\Platform\Sqlite\Ir\ColumnReference(0),
                1,
            );
            $condition = $detached->checks[0];
            if (!$condition->label instanceof SqlSemantics\Platform\Sqlite\Ir\Identifier
                || !$condition->condition instanceof SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison
                || !$condition->condition->left instanceof SqlSemantics\Platform\Sqlite\Ir\ColumnReference
                || !$condition->condition->right instanceof SqlSemantics\Platform\Sqlite\Ir\ColumnReference) {
                throw new RuntimeException('The installed ordinary addition lost its checked slots.');
            }
            $details['appendedChecks'] = [$condition->label->value, $condition->column, $condition->condition->left->slot,
                $condition->condition->right->slot, $detached->toString()];
            $implicit = $semantics->analyze('ALTER TABLE main.accounts ADD total INTEGER CHECK (TOTAL >= 007)', $context);
            $condition = $implicit->checks[0];
            if (!$condition->label instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ImplicitCheckLabel
                || !$condition->condition instanceof SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison
                || !$condition->condition->left instanceof SqlSemantics\Platform\Sqlite\Ir\ColumnReference) {
                throw new RuntimeException('The installed implicit comparison lost its checked source slot.');
            }
            $details['implicitAppendedCheck'] = [$condition->label->columnName->value, $condition->label->token->value,
                $condition->label->leadingZeroes, $condition->condition->left->slot, $implicit->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Alter\\AddGeneratedColumn') {
            $generation = $result->column->generated;
            if (!$generation instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\ColumnGeneration
                || !$generation->expression instanceof SqlSemantics\Platform\Sqlite\Ir\Arithmetic
                || !$generation->expression->left instanceof SqlSemantics\Platform\Sqlite\Ir\ColumnReference
                || !$generation->expression->right instanceof SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral) {
                throw new RuntimeException('The installed generated column addition lost its checked input roles.');
            }
            $first = $result->priorHeading[0];
            $heading = $result->priorHeading;
            $heading[0] = &$first;
            $direct = new SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\AddGeneratedColumn(
                $result->schema, $result->table, $heading, $result->strict, $result->column,
            );
            $first = $result->column->name;
            $details['addGeneratedColumn'] = [$result->schema->value, $result->table->value, array_column($direct->priorHeading, 'value'),
                $result->strict, $result->column->name->value, $generation->mode->value, $generation->expression->operator->value,
                $generation->expression->left->slot, $generation->expression->right->decimal, $direct->toString()];
            $checked = $semantics->analyze(
                'ALTER TABLE main.accounts ADD total INTEGER AS (id + 7) VIRTUAL CONSTRAINT "large" CHECK (total > id)',
                $context,
            );
            $condition = $checked->checks[0];
            if (!$condition->label instanceof SqlSemantics\Platform\Sqlite\Ir\Identifier
                || !$condition->condition instanceof SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison
                || !$condition->condition->left instanceof SqlSemantics\Platform\Sqlite\Ir\ColumnReference
                || !$condition->condition->right instanceof SqlSemantics\Platform\Sqlite\Ir\ColumnReference) {
                throw new RuntimeException('The installed generated addition lost its checked slots.');
            }
            $details['appendedChecks'] = [$condition->label->value, $condition->column, $condition->condition->left->slot,
                $condition->condition->right->slot, $checked->toString()];
            $carried = $semantics->analyze(
                'ALTER TABLE main.accounts ADD total AS (id + 7) CONSTRAINT range CHECK (total > id) CHECK (total < 100)',
                $context,
            );
            $details['carriedAppendedChecks'] = [$carried->checks[0]->label->value, $carried->checks[1]->label->value, $carried->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Alter\\DropColumn') {
            $selected = $result->heading[$result->slot];
            $heading = $result->heading;
            $heading[$result->slot] = &$selected;
            $detached = new SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropColumn($result->schema, $result->table, $heading, $result->slot);
            $selected = new SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropMember(new SqlSemantics\Platform\Sqlite\Ir\Identifier('other'));
            $protected = false;
            try {
                $semantics->analyze('ALTER TABLE removable DROP keep', $activeContext);
            } catch (SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction $error) {
                $protected = str_contains($error->getMessage(), 'declaration-local removal restriction');
            }
            $details['dropColumn'] = [$result->schema->value, $result->table->value, $result->slot,
                array_map(static fn ($member): array => [$member->name->value, $member->generated, $member->protected], $detached->heading),
                $protected, $detached->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Connection\\Attach') {
            $file = $result->file->values[0];
            $key = $result->key?->values[0];
            if (!$file instanceof SqlSemantics\Platform\Sqlite\Ir\Parameter
                || !$result->name instanceof SqlSemantics\Platform\Sqlite\Ir\Connection\AttachmentName
                || !$key instanceof SqlSemantics\Platform\Sqlite\Ir\Parameter) {
                throw new RuntimeException('The installed ATTACH lost its typed filename, name or key.');
            }
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Connection\Attach($result->file, $result->name, $result->key);
            $details['connectionAttach'] = [$file->label, $result->name->name->value, $key->label, $rebuilt->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Connection\\Detach') {
            $target = $result->target;
            $dynamic = $semantics->analyze('DETACH ?005', $activeContext);
            if (!$target instanceof SqlSemantics\Platform\Sqlite\Ir\Connection\DetachTarget
                || !$dynamic instanceof SqlSemantics\Platform\Sqlite\Ir\Connection\Detach
                || !$dynamic->target instanceof SqlSemantics\Platform\Sqlite\Ir\Parameter) {
                throw new RuntimeException('The installed DETACH lost the attachment identity or numbered selector.');
            }
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Connection\Detach($target);
            $details['connectionDetach'] = [$target->schema->value, $target->name->name->value, $rebuilt->toString(),
                $dynamic->target->label, $dynamic->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Vacuum\\Vacuum') {
            $skipped = $semantics->analyze('VACUUM temp INTO (missing + ?011)', $activeContext);
            $operand = $result->destination;
            if (!$operand instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumDestination
                || !$operand->expression->values[0] instanceof SqlSemantics\Platform\Sqlite\Ir\Parameter
                || !$skipped instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\Vacuum
                || !$skipped->destination instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumRegistrations) {
                throw new RuntimeException('The installed VACUUM lost an evaluated operand or inert registration.');
            }
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\Vacuum($result->schema, $operand);
            $default = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\Vacuum();
            $details['vacuum'] = [$result->schema->value, $operand->expression->values[0]->label, $rebuilt->toString(),
                $default->toString(), $skipped->schema->value, $skipped->destination->bindings[0]->label, $skipped->toString()];
        }
        if ($result instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger) {
            $step = $result->program[0];
            $old = $step->fields[0]->value;
            $new = $step->fields[1]->value;
            $future = $result->selectors[1]->selector;
            if (!$old instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField
                || !$new instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField
                || !$future instanceof SqlSemantics\Platform\Sqlite\Ir\Identifier) {
                throw new RuntimeException('The installed trigger lost its image or update selector roles.');
            }
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger(
                $result->schema, $result->name, $result->placement, $result->watched, $result->timing, $result->event,
                $result->program, $result->selectors, $result->condition, $result->ifNotExists,
            );
            $details['auditTrigger'] = [$result->watched->schema->value, $step->destination->table->schema->value,
                $old->image->value, $old->column, $new->image->value, $new->column,
                $result->selectors[0]->selector, $future->value, $result->condition?->isNull, $copy->toString()];
        }
        if ($result instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSize) {
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSize($result->schema, $result->amount);
            $details['cacheSize'] = [$copy->schema->value, $copy->amount?->value, $copy->toString()];
        }
        if ($result instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCacheSize) {
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCacheSize($result->schema, $result->capacity);
            $details['defaultCacheSize'] = [$copy->schema->value, $copy->capacity?->pages, $copy->toString()];
        }
        if ($result instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSpill) {
            $disabled = $semantics->analyze('PRAGMA ARCHIVE.cache_spill(256)', $activeContext);
            if (!$result->change instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillThreshold
                || !$disabled instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSpill
                || !$disabled->change instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillThreshold) {
                throw new RuntimeException('The installed cache spill threshold lost its independent effect.');
            }
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSpill($result->schema, $result->change);
            $details['cacheSpill'] = [$result->schema->value, $result->change->amount->value, $result->change->mode->value,
                $copy->toString(), $disabled->change->amount->value, $disabled->change->mode->value, $disabled->toString()];
        }
        if ($result instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\AutoVacuum) {
            $reset = $semantics->analyze('PRAGMA auto_vacuum(3)', $activeContext);
            if (!$reset instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\AutoVacuum) {
                throw new RuntimeException('The installed auto-vacuum lost its explicit mode request.');
            }
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\AutoVacuum($result->schema, $result->mode);
            $details['autoVacuum'] = [$result->schema->value, $result->mode?->value, $copy->toString(), $reset->mode?->value, $reset->toString()];
        }
        if ($result instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\IncrementalVacuum) {
            $default = $semantics->analyze('PRAGMA temp.incremental_vacuum(-1)', $activeContext);
            if (!$default instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\IncrementalVacuum) {
                throw new RuntimeException('The installed incremental vacuum lost its native maximum.');
            }
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\IncrementalVacuum($result->schema, $result->limit);
            $details['incrementalVacuum'] = [$result->schema->value, $result->limit->pages, $copy->toString(), $default->limit->pages, $default->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Page\\PagePragma') {
            $pending = $semantics->analyze('PRAGMA temp.page_size(DEFAULT)', $activeContext);
            $count = $semantics->analyze('PRAGMA archive.page_count(17)', $activeContext);
            if (!$result->value instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PageLimit
                || !$pending instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragma
                || !$pending->value instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\RequestedPageSize
                || !$count instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragma) {
                throw new RuntimeException('The installed pager pragma lost its native argument role.');
            }
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragma($result->kind, $result->schema, $result->value);
            $details['pagePragma'] = [$result->schema->value, $result->value->decimal, $copy->toString(),
                $pending->schema->value, $pending->value->bytes, $pending->toString(), $count->value, $count->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Header\\HeaderPragma') {
            $readonly = $semantics->analyze("PRAGMA temp.data_version('ignored')", $activeContext);
            if (!$readonly instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderPragma) {
                throw new RuntimeException('The installed header pragma lost its read-only field.');
            }
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderPragma($result->field, $result->schema, $result->value);
            $details['headerPragma'] = [$result->field->value, $result->schema->value, $result->value?->value,
                $rebuilt->toString(), $readonly->field->value, $readonly->schema->value,
                $readonly->value?->value, $readonly->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Integrity\\IntegrityAudit') {
            $target = $result->target;
            $bounded = $semantics->analyze('PRAGMA main.integrity_check(-2)', $activeContext);
            $catalog = $semantics->analyze('PRAGMA quick_check(sqlite_temp_schema)', $activeContext);
            if (!$target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget
                || !$bounded instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAudit
                || !$bounded->target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityLimit
                || !$catalog instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAudit
                || !$catalog->target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable) {
                throw new RuntimeException('The installed integrity audit lost its native target or budget.');
            }
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAudit($result->kind, $target, $result->schema);
            $details['integrityAudit'] = [$result->kind->value, $target->schema->value, $target->name->value, $result->schema?->value,
                $rebuilt->toString(), $bounded->target->value, $bounded->toString(),
                $catalog->target->schema->value, $catalog->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Metadata\\ForeignKeyAudit') {
            $target = $result->target;
            $default = $semantics->analyze('PRAGMA foreign_key_check', $activeContext);
            $catalog = $semantics->analyze('PRAGMA foreign_key_check(sqlite_temp_schema)', $activeContext);
            if (!$target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget
                || !$default->target instanceof SqlSemantics\Platform\Sqlite\Ir\Identifier
                || !$catalog->target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable) {
                throw new RuntimeException('The installed foreign-key audit lost its selected native scope.');
            }
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\ForeignKeyAudit($target);
            $details['foreignKeyAudit'] = [$target->schema->value, $target->name->value, $rebuilt->toString(), $default->target->value,
                $default->toString(), $catalog->target->schema->value, $catalog->catalogAccess?->value, $catalog->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Metadata\\IndexInspection') {
            $target = $result->target;
            $named = $semantics->analyze('PRAGMA main.index_xinfo(sqlite_autoindex_storage_table_2)', $activeContext);
            $empty = $semantics->analyze('PRAGMA temp.index_info(absent)', $activeContext);
            if (!$target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget
                || !$named->target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget
                || !$empty->target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\EmptyIndexMetadata) {
                throw new RuntimeException('The installed index metadata query lost its physical selector.');
            }
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspection($result->kind, $target);
            $details['indexMetadata'] = [$target->schema->value, $target->name->value, $target->table->value,
                $named->target->name->value, $copy->toString(), $empty->target->schema?->value, $empty->target->name?->value, $empty->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Metadata\\TableInspection') {
            $target = $result->target;
            $catalog = $semantics->analyze('PRAGMA table_info(sqlite_temp_schema)', $activeContext);
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection($result->kind, $target, $result->schema);
            if (!$target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget
                || !$catalog->target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\CatalogTable) {
                throw new RuntimeException('The installed metadata query lost its table role.');
            }
            $details['tableInspection'] = [$result->kind->value, $target->schema->value, $target->name->value, $result->schema?->value,
                $catalog->target->schema->value, $catalog->toString(), $rebuilt->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Metadata\\TableListing') {
            $empty = $semantics->analyze("PRAGMA temp.table_list('')", $activeContext);
            $details['tableListing'] = [$result->schema?->value, $result->nameFilter?->value, $empty->nameFilter?->value, $empty->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Inventory\\InventoryPragma') {
            $all = $semantics->analyze("PRAGMA PRAGMA_LIST('ignored')", $activeContext);
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma($result->kind, $result->schema);
            $details['connectionInventory'] = [$result->kind->value, $result->schema?->value, $rebuilt->toString(),
                $all->kind->value, $all->schema?->value, $all->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\FlagPragma') {
            $disabled = $semantics->analyze('PRAGMA automatic_index(256)', $activeContext);
            $read = $semantics->analyze('PRAGMA MAIN.FOREIGN_KEYS', $activeContext);
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragma($result->name, $result->change, $result->schema);
            $rejected = false;
            try {
                new SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragma(
                    SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagName::ForeignKeys,
                    SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagChange::Reset,
                );
            } catch (SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction $error) {
                $rejected = str_contains($error->getMessage(), 'Only writable_schema');
            }
            $details['flagPragma'] = [$result->name->value, $result->change?->value, $result->schema?->value, $copy->toString(),
                $disabled->change?->value, $disabled->toString(), $read->change, $read->toString(), $rejected];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Analyze') {
            $index = $result->target;
            $schema = $semantics->analyze('ANALYZE EXTENSION', $activeContext);
            $view = $semantics->analyze('ANALYZE main.stats_view', $activeContext);
            if (!$index instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget
                || !$schema->target instanceof SqlSemantics\Platform\Sqlite\Ir\Identifier
                || !$view->target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget) {
                throw new RuntimeException('The installed statistics operation lost its resolved target role.');
            }
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Analyze(
                new SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget($index->schema, $index->name, $index->table),
            );
            $details['analyzeStatistics'] = [$index->schema->value, $index->name->value, $index->table->value,
                $schema->target->value, $schema->toString(), $view->target->kind->value, $view->toString(), $rebuilt->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Maintenance\\Reindex') {
            $relation = $result->target;
            $builtin = $semantics->analyze('REINDEX NoCase', $activeContext);
            $index = $semantics->analyze('REINDEX temp.names', $activeContext);
            if (!$relation instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\RelationTarget
                || !$index->target instanceof SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget
                || !$builtin->target instanceof SqlSemantics\Platform\Sqlite\Ir\Collation) {
                throw new RuntimeException('The installed rebuild did not retain native namespace precedence.');
            }
            $bound = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget(
                $index->target->schema, $index->target->name, $index->target->table,
            );
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reindex($bound);
            $details['reindex'] = [
                $relation->schema->value, $relation->name->value, $relation->kind->value,
                $builtin->target->value, $builtin->toString(), $bound->table->value, $index->toString(), $rebuilt->toString(),
            ];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Index\\DropIndex') {
            $target = $result->target;
            if (!$target instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexReference) {
                throw new RuntimeException('The installed DROP INDEX lost its absolute index target.');
            }
            $direct = new SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndex(
                new SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexReference(
                    new SqlSemantics\Platform\Sqlite\Ir\Identifier('temp'),
                    new SqlSemantics\Platform\Sqlite\Ir\Identifier('tAg'),
                    new SqlSemantics\Platform\Sqlite\Ir\Identifier('local_rows'),
                ),
            );
            $absent = $semantics->analyze('DROP INDEX IF EXISTS ""."later"', $activeContext);
            $unqualified = $semantics->analyze('DROP INDEX IF EXISTS "later"', $activeContext);
            if (!$absent->target instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Index\MissingIndex
                || !$unqualified->target instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Index\MissingIndex) {
                throw new RuntimeException('The installed DROP INDEX lost the guarded absence.');
            }
            $refused = false;
            try {
                new SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndex($absent->target);
            } catch (SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction $error) {
                $refused = str_contains($error->getMessage(), 'requires IF EXISTS');
            }
            $details['dropIndex'] = [$target->schema->value, $target->name->value, $target->table->value,
                $direct->toString(), $absent->target->schema?->value, $absent->toString(),
                $unqualified->target->schema?->value, $unqualified->toString(), $refused];
            $constraints = $semantics->context([
                'CREATE TABLE main.known_index (id INTEGER PRIMARY KEY, code TEXT UNIQUE) WITHOUT ROWID',
            ]);
            $missing = $semantics->analyze('DROP INDEX IF EXISTS main.sqlite_autoindex_known_index_3', $constraints);
            $protected = false;
            try {
                $semantics->analyze('DROP INDEX IF EXISTS main.sqlite_autoindex_known_index_2', $constraints);
            } catch (SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction $error) {
                $protected = str_contains($error->getMessage(), 'cannot drop a UNIQUE or PRIMARY KEY constraint index');
            }
            $details['constraintIndexNames'] = [
                $missing->target instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Index\MissingIndex,
                $missing->toString(), $protected,
            ];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Index\\CreateIndex') {
            $term = $result->terms[0];
            $keys = [&$term];
            $detached = new SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndex(
                $result->schema, $result->index, $result->table, $result->heading, $keys,
                $result->where, $result->unique, $result->ifNotExists,
            );
            $term = new SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexTerm(new SqlSemantics\Platform\Sqlite\Ir\ColumnReference(0));
            $expression = $detached->terms[0]->expression;
            if (!$expression instanceof SqlSemantics\Platform\Sqlite\Ir\ColumnReference
                || !$detached->where instanceof SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership) {
                throw new RuntimeException('The installed index lost its key and predicate roles.');
            }
            $details['createIndex'] = [$result->schema->value, $result->index->value, $result->table->value,
                $expression->slot, $detached->terms[0]->descending, $detached->unique, $detached->ifNotExists,
                array_column($detached->where->bindings, 'label'), $detached->toString()];
            $named = $semantics->analyze('CREATE INDEX native_cast ON indexed_rows(IFNULL(CAST(id AS int), 0))', $activeContext);
            $function = $named->terms[0]->expression;
            if (!$function instanceof SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce) {
                throw new RuntimeException('The installed index lost the native builtin.');
            }
            $cast = $function->arguments[0]->expression;
            if (!$cast instanceof SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression || $cast->typeName === null
                || !$cast->operand instanceof SqlSemantics\Platform\Sqlite\Ir\ColumnReference) {
                throw new RuntimeException('The installed index lost the native CAST name or its bound input.');
            }
            $type = new SqlSemantics\Platform\Sqlite\Ir\Expression\CastTypeName('int');
            $scope = new SqlSemantics\Platform\Sqlite\Ir\ColumnReference(0);
            $copy = new SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression($type->target(), $scope, $type);
            $direct = new SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce([
                new SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument($copy),
                new SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument(new SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('0')),
            ], true);
            $rebuilt = new SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndex(
                $named->schema, $named->index, $named->table, $named->heading,
                [new SqlSemantics\Platform\Sqlite\Ir\Schema\Index\IndexTerm($direct)],
            );
            $mismatched = false;
            try {
                new SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression(
                    SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget::Blob, $scope, $type,
                );
            } catch (SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction $error) {
                $mismatched = str_contains($error->getMessage(), 'select the effective CAST target');
            }
            $details['sqliteIndexExpressionSpelling'] = [$function->ifNull, $cast->typeName->value,
                $cast->target->value, $cast->operand->slot, $mismatched, $named->toString(), $rebuilt->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\Query\\CreateTableAs') {
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
