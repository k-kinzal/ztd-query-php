<?php

declare(strict_types=1);

use SqlSemantics\AnalysisException;
use SqlSemantics\SemanticsIR;

$root = $argv[1];
$overlay = $argv[2] ?? '';
spl_autoload_register(static function (string $class) use ($root, $overlay): void {
    foreach ([
        'SqlSemantics\\Platform\\MySql\\' => 'sql-semantics-mysql',
        'SqlSemantics\\Platform\\PostgreSql\\' => 'sql-semantics-postgres',
        'SqlSemantics\\' => 'sql-semantics',
        'SqlParser\\' => 'sql-parser',
    ] as $prefix => $package) {
        if (str_starts_with($class, $prefix)) {
            $relative = $package . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            $patched = $overlay . '/' . $relative;
            require $overlay !== '' && is_file($patched) ? $patched : $root . '/packages/' . $relative;

            return;
        }
    }
});

function constructOperand(string $namespace, array $data): object
{
    $type = $namespace . match ($data[0]) {
        'integer' => 'IntegerLiteral',
        'wide-integer' => 'Expression\\WideIntegerLiteral',
        'text', 'text-bytes' => str_contains($namespace, '\\MySql\\') ? 'Literal\\TextLiteral' : 'Expression\\TextLiteral',
        'collated-text' => 'Expression\\CollatedText',
        'text-comparison' => 'Predicate\\Text\\TextComparison',
        'parameter' => 'Binding\\Parameter',
        'boolean' => 'BooleanLiteral',
        'null' => 'NullLiteral',
        'wide-null' => 'Expression\\WideIntegerNull',
        'slot' => 'ColumnReference',
        'text-slot' => 'From\\TextColumnReference',
        'bool-slot' => 'From\\BooleanColumnReference',
        'wide-slot' => 'From\\WideIntegerColumnReference',
        'operation' => 'Arithmetic',
        'comparison' => 'Predicate\\IntegerComparison',
        'int4-case' => 'Expression\\IntegerCase',
        'builtin-cast' => 'Expression\\BuiltinCast',
        'signed-cast' => 'Expression\\SignedCast',
        'unsigned-cast' => 'Expression\\UnsignedCast',
        'int4-range', 'signed-range' => 'Predicate\\IntegerRange',
        'int4-distinctness' => 'Predicate\\IntegerDistinctness',
        'truth-test' => 'Predicate\\TruthTest',
        'null-test' => 'Predicate\\NullTest',
        'range-integer' => 'Predicate\\RangeIntegerLiteral',
    };
    $operator = $namespace . 'ArithmeticOperator';
    $comparison = $namespace . 'Predicate\\IntegerOperator';
    $castTarget = $namespace . 'Expression\\CastDestination';
    $collation = $namespace . (str_contains($namespace, '\\MySql\\') ? 'From\\TextCollation' : 'Expression\\TextCollation');
    $textOperator = $namespace . 'Predicate\\Text\\TextOperator';
    $truth = $namespace . 'Predicate\\TruthOperator';

    return match ($data[0]) {
        'integer', 'range-integer', 'wide-integer' => new $type(...array_slice($data, 1)),
        'comparison' => new $type($comparison::from($data[1]), constructOperand($namespace, $data[2]), constructOperand($namespace, $data[3])),
        'text' => new $type($data[1]),
        'text-bytes' => new $type(hex2bin($data[1])),
        'collated-text' => new $type(constructOperand($namespace, $data[2]), $collation::from($data[1])),
        'text-comparison' => new $type(
            $textOperator::from($data[1]),
            constructOperand($namespace, $data[3]),
            constructOperand($namespace, $data[4]),
            $collation::from($data[2])
        ),
        'boolean' => new $type($data[1]),
        'parameter' => isset($data[2]) ? new $type($data[1], $castTarget::from($data[2])) : new $type($data[1]),
        'slot', 'text-slot', 'bool-slot', 'wide-slot' => new $type($data[1], $data[2] ?? 0),
        'null', 'wide-null' => new $type(),
        'operation' => new $type(
            $operator::from($data[1]),
            constructOperand($namespace, $data[2]),
            constructOperand($namespace, $data[3]),
        ),
        'signed-cast', 'unsigned-cast' => new $type(constructOperand($namespace, $data[1])),
        'builtin-cast' => new $type($castTarget::from($data[1]), constructOperand($namespace, $data[2])),
        'truth-test' => new $type($truth::from($data[1]), constructPredicate($namespace, $data[2])),
        'null-test' => new $type(constructPredicate($namespace, $data[2]), $data[1]),
        'int4-distinctness' => new $type(
            constructOperand($namespace, $data[2]),
            constructOperand($namespace, $data[3]),
            $data[1],
        ),
        'signed-range' => new $type(
            constructOperand($namespace, $data[2]),
            constructOperand($namespace, $data[3]),
            constructOperand($namespace, $data[4]),
            $data[1],
        ),
        'int4-range' => new $type(
            constructOperand($namespace, $data[3]),
            constructOperand($namespace, $data[4]),
            constructOperand($namespace, $data[5]),
            $data[1],
            $data[2],
        ),
        'int4-case' => new $type(
            array_map(static function (array $branch) use ($namespace): object {
                $arm = $namespace . 'Expression\\IntegerCaseArm';

                return new $arm(constructPredicate($namespace, $branch[0]), constructOperand($namespace, $branch[1]));
            }, $data[1]),
            $data[2] === null ? null : constructOperand($namespace, $data[2]),
        ),
    };
}

function observeOperand(string $namespace, object $value): array
{
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\IntegerLiteral
        && ($value->leadingZeroes !== 0 || $value->negativeZero)) {
        return ['integer', $value->decimal, $value->leadingZeroes, $value->negativeZero];
    }
    return match ($value::class) {
        $namespace . 'IntegerLiteral' => ['integer', $value->decimal],
        $namespace . 'Expression\\WideIntegerLiteral' => ['wide-integer', $value->decimal],
        $namespace . 'Expression\\TextLiteral', $namespace . 'Literal\\TextLiteral' => ['text', $value->value],
        $namespace . 'Expression\\CollatedText' => ['collated-text', $value->collation->value, observeOperand($namespace, $value->operand)],
        $namespace . 'Predicate\\Text\\TextComparison' => ['text-comparison', $value->operator->value, $value->collation->value,
            observeOperand($namespace, $value->left), observeOperand($namespace, $value->right)],
        $namespace . 'Binding\\Parameter' => match (true) {
            !($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter),
            $value->input === SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination::Integer
                => ['parameter', $value->position],
            default => ['parameter', $value->position, $value->input->value],
        },
        $namespace . 'BooleanLiteral' => ['boolean', $value->value],
        $namespace . 'NullLiteral' => ['null'],
        $namespace . 'Expression\\WideIntegerNull' => ['wide-null'],
        $namespace . 'ColumnReference' => $value->occurrence === 0 ? ['slot', $value->slot] : ['slot', $value->slot, $value->occurrence],
        $namespace . 'From\\TextColumnReference' => ['text-slot', $value->slot, $value->occurrence],
        $namespace . 'From\\BooleanColumnReference' => ['bool-slot', $value->slot, $value->occurrence],
        $namespace . 'From\\WideIntegerColumnReference' => ['wide-slot', $value->slot, $value->occurrence],
        $namespace . 'Predicate\\IntegerComparison' => observePredicate($namespace, $value),
        $namespace . 'Arithmetic' => ['operation', $value->operator->value,
            observeOperand($namespace, $value->left), observeOperand($namespace, $value->right)],
        $namespace . 'Expression\\SignedCast' => ['signed-cast', observeOperand($namespace, $value->operand)],
        $namespace . 'Expression\\UnsignedCast' => ['unsigned-cast', observeOperand($namespace, $value->operand)],
        $namespace . 'Expression\\BuiltinCast' => ['builtin-cast', $value->target->value, observeOperand($namespace, $value->operand)],
        $namespace . 'Predicate\\NullTest' => ['null-test', $value->negated, observePredicate($namespace, $value->operand)],
        $namespace . 'Predicate\\TruthTest' => ['truth-test', $value->operator->value, observePredicate($namespace, $value->operand)],
        $namespace . 'Predicate\\IntegerDistinctness' => ['int4-distinctness', $value->negated,
            observeOperand($namespace, $value->left), observeOperand($namespace, $value->right)],
        $namespace . 'Predicate\\IntegerRange' => [
            ...($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange
                ? ['int4-range', $value->negated, $value->symmetric] : ['signed-range', $value->negated]),
            observeOperand($namespace, $value->subject), observeOperand($namespace, $value->lower), observeOperand($namespace, $value->upper),
        ],
        $namespace . 'Predicate\\RangeIntegerLiteral' => ['range-integer', $value->decimal, $value->leadingZeroes, $value->negativeZero],
        $namespace . 'Expression\\IntegerCase' => ['int4-case',
            array_map(static fn ($arm) => [observePredicate($namespace, $arm->condition), observeOperand($namespace, $arm->result)], $value->arms),
            $value->otherwise === null ? null : observeOperand($namespace, $value->otherwise)],
    };
}

function constructSource(string $platform, array $source, string $dialect): object
{
    $namespace = $platform . 'Ir\\';
    if (isset($source['kind'])) {
        $join = $namespace . 'From\\Join';
        $kind = $namespace . 'From\\JoinKind';

        return new $join(
            $kind::from($source['kind']),
            constructSource($platform, $source['left'], $dialect),
            constructSource($platform, $source['right'], $dialect),
            constructPredicate($namespace, $source['on']),
        );
    }
    if (isset($source['derived'])) {
        $derived = $namespace . 'From\\DerivedSource';
        return new $derived(constructSelect($platform, $source['derived']));
    }
    $identifier = $namespace . 'Identifier';
    $table = $namespace . 'TableSource';

    $types = $dialect === 'postgres' ? [
        $source['only'],
        array_map($namespace . 'From\\ColumnKind::from', $source['columnKinds'] ?? []),
        ($namespace . 'From\\ExternalRelationKind')::from($source['relationKind'] ?? 'table'),
    ] : [
        array_map(constructMySqlColumnType(...), $source['columnTypes'] ?? []),
        ($namespace . 'From\\ExternalRelationKind')::from($source['relationKind'] ?? 'table'),
    ];

    return new $table(
        new $identifier($source['schema']),
        new $identifier($source['table']),
        array_map(static fn ($name) => new $identifier($name), $source['columns']),
        ...$types,
    );
}

function constructMySqlColumnType(array|string $input): object
{
    if (is_string($input)) {
        return SqlSemantics\Platform\MySql\Ir\From\ColumnKind::from($input);
    }
    if ($input[0] !== 'varchar') {
        throw new RuntimeException('Unknown MySQL column type role.');
    }

    return new SqlSemantics\Platform\MySql\Ir\From\Varchar(
        $input[1],
        SqlSemantics\Platform\MySql\Ir\From\TextCollation::from($input[2]),
        $input[3] ?? true,
    );
}

function constructResultOptions(string $namespace, array $input, string $dialect): array
{
    $order = $namespace . 'Ordering\\Order';
    $limit = $namespace . 'Ordering\\Limit';
    $keys = [];
    foreach ($input['orderBy'] ?? [] as $raw) {
        if ($raw === null) {
            $keys[] = null;
            continue;
        }
        $target = is_array($raw[0]) ? constructOperand($namespace, $raw[0]) : $raw[0];
        $keys[] = new $order($target, $raw[1], ...($dialect === 'postgres' ? [$raw[2]] : []));
    }
    $bound = $input['limit'] ?? null;
    return [$keys, $bound === null ? null : new $limit($bound[0], $bound[1])];
}

function constructSelect(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $identifier = $namespace . 'Identifier';
    $column = $namespace . 'ResultColumn';
    $select = $namespace . 'Select';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    [$keys, $bound] = constructResultOptions($namespace, $input, $input['dialect']);
    $from = $input['source'] === null ? null : constructSource($platform, $input['source'], $input['dialect']);
    foreach ($input['columns'] as [$expr, $name]) {
        $columns[] = new $column(constructOperand($namespace, $expr), new $identifier($name));
    }

    return new $select(
        $columns ?? [],
        $from,
        $release::from($input['release']),
        constructPredicate($namespace, $input['where'] ?? null),
        $input['distinct'] ?? false,
        $keys,
        $bound,
    );
}

function analyzeStatement(string $platform, array $input): SemanticsIR
{
    $class = $platform . 'Semantics';
    $searchPath = $platform . 'Model\\Contract\\SearchPath';
    $semantics = isset($input['schemaPath'])
        ? new $class($input['release'], searchPath: new $searchPath(...$input['schemaPath']))
        : (isset($input['currentDatabase'])
        ? new $class($input['release'], searchPath: new $searchPath($input['currentDatabase']))
        : new $class($input['release']));
    return $semantics->analyze($input['sql'], $semantics->context($input['declarations'] ?? null, $input['complete'] ?? true));
}

function observeSource(object $source, string $dialect, string $namespace): array
{
    if ($source::class === $namespace . 'From\\Join') {
        return [
            'kind' => $source->kind->value,
            'left' => observeSource($source->left, $dialect, $namespace),
            'right' => observeSource($source->right, $dialect, $namespace),
            'on' => observePredicate($namespace, $source->on),
        ];
    }

    if ($source::class === $namespace . 'From\\DerivedSource') {
        return ['derived' => observeSetQuery($namespace, $source->definition, $dialect)];
    }
    if ($source::class !== $namespace . 'TableSource') {
        throw new RuntimeException('Unknown native table source role.');
    }

    $columns = [
        'schema' => $source->schema->value,
        'table' => $source->table->value,
        'columns' => array_map(static fn ($name): string => $name->value, $source->columns),
    ];
    if ($source->kind->value !== 'table') {
        $columns['relationKind'] = $source->kind->value;
    }
    if ($dialect === 'postgres') {
        $columns['only'] = $source->only;
        $kinds = array_column($source->columnKinds, 'value');
        if ($kinds !== [] && $kinds !== array_fill(0, count($columns['columns']), 'int4')) {
            $columns['columnKinds'] = $kinds;
        }
    } else {
        $types = array_map(
            static fn ($type): array|string => $type instanceof SqlSemantics\Platform\MySql\Ir\From\Varchar
                ? ['varchar', $type->length, $type->collation->value, ...($type->nullable ? [] : [false])]
                : $type->value,
            $source->columnTypes,
        );
        if ($types !== array_fill(0, count($columns['columns']), 'int')) {
            $columns['columnTypes'] = $types;
        }
    }
    return $columns;
}

function constructPredicate(string $namespace, ?array $test): ?object
{
    if ($test === null) {
        return null;
    }
    $logical = $namespace . 'Predicate\\LogicalPredicate';
    $logicalOperator = $namespace . 'Predicate\\LogicalOperator';
    $nullTest = $namespace . 'Predicate\\NullTest';
    return match ($test[0]) {
        'comparison' => constructOperand($namespace, $test),
        'logic' => new $logical(
            $logicalOperator::from($test[1]),
            constructPredicate($namespace, $test[2]),
            constructPredicate($namespace, $test[3] ?? null),
        ),
        'null-test' => new $nullTest(constructPredicate($namespace, $test[2]), $test[1]),
        'boolean', 'null', 'slot', 'text-slot', 'bool-slot', 'wide-slot', 'integer', 'text-comparison', 'int4-range', 'signed-range', 'int4-distinctness', 'truth-test' => constructOperand($namespace, $test),
    };
}

function constructLocalProjection(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Rows\\LocalProjection';
    $column = $namespace . 'Rows\\LocalResultColumn';
    $name = $namespace . 'Identifier';
    $local = $input['local'];
    $columns = [];
    foreach ($local['columns'] as [$slot, $label]) {
        $columns[] = new $column($slot, new $name($label));
    }
    $definition = $local['definition'] + array_intersect_key($input, ['dialect' => true, 'release' => true]);
    $source = match (true) {
        isset($definition['local']) => constructLocalProjection($platform, $definition),
        isset($definition['with']) => constructWith($platform, $definition),
        isset($definition['postgresValues']) => constructPostgresValues($platform, $definition),
        default => constructSelect($platform, $definition),
    };

    return new $statement($source, $columns);
}

function constructWith(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\Rows\\With\\';
    $type = $namespace . 'WithProjection';
    $def = $namespace . 'WithDefinition';
    $policy = $namespace . 'WithMaterialization';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $with = $input['with'];
    $definitions = [];
    foreach ($with['definitions'] as [$materialization, $query]) {
        $body = constructWithMember($platform, $query, $input['dialect'], $input['release']);
        $definitions[] = $input['dialect'] === 'mysql' ? new $def($body) : new $def($body, $policy::from($materialization));
    }
    return new $type($definitions, constructWithMember($platform, $with['body'], $input['dialect'], $input['release']), $release::from($input['release']));
}

function constructWithMember(string $platform, array $input, string $dialect, string $release): object
{
    if (isset($input['read'])) {
        [$ordinal, $columns] = $input['read'];
        $type = $platform . 'Ir\\Rows\\With\\WithRead';
        $result = $platform . 'Ir\\Rows\\LocalResultColumn';
        $identifier = $platform . 'Ir\\Identifier';
        return new $type($ordinal, array_map(static fn ($column) => new $result($column[0], new $identifier($column[1])), $columns));
    }
    $effective = $input + ['dialect' => $dialect, 'release' => $release];
    return match (true) {
        isset($input['with']) => constructWith($platform, $effective),
        isset($input['local']) => constructLocalProjection($platform, $effective),
        isset($input['postgresValues']) => constructPostgresValues($platform, $effective),
        default => constructSelect($platform, $effective),
    };
}

function constructRecursiveSeries(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $type = $namespace . 'Rows\\RecursiveSeries';
    $column = $namespace . 'Rows\\LocalResultColumn';
    $identifier = $namespace . 'Identifier';
    $operator = $namespace . 'Predicate\\IntegerOperator';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $series = $input['series'];
    $columns = [];
    foreach ($series['columns'] as [$slot, $name]) {
        $columns[] = new $column($slot, new $identifier($name));
    }

    return new $type(
        constructOperand($namespace, $series['start']),
        constructOperand($namespace, $series['increment']),
        constructOperand($namespace, $series['boundary']),
        $operator::from($series['comparison']),
        $series['all'],
        $columns,
        $release::from($input['release']),
    );
}

function constructSetQuery(string $platform, array $input): SemanticsIR
{
    if (isset($input['postgresValues'])) {
        return constructPostgresValues($platform, $input);
    }
    if (!isset($input['compound'])) {
        return constructSelect($platform, $input);
    }
    $namespace = $platform . 'Ir\\Rows\\';
    $type = $namespace . 'Compound';
    $operator = $namespace . 'CompoundOperator';
    $tree = $input['compound'];

    $profile = array_intersect_key($input, ['dialect' => true, 'release' => true]);
    return new $type(
        constructSetQuery($platform, $tree['left'] + $profile),
        $operator::from($tree['operator']),
        $tree['all'],
        constructSetQuery($platform, $tree['right'] + $profile),
    );
}

function mysqlSetKinds(SqlSemantics\Platform\MySql\Ir\Rows\Compound $value): array
{
    $shape = SqlSemantics\Platform\MySql\Ir\Rows\CompoundShape::class;
    $kinds = [];
    for ($position = 0; $position < $shape::width($value); ++$position) {
        $kinds[] = $shape::type($value, $position)->value;
    }

    return $kinds;
}

function observeSetQuery(string $namespace, object $value, string $dialect): array
{
    if ($value::class === $namespace . 'Rows\\Values\\ValuesQuery') {
        return postgresValuesGraph($namespace, $value);
    }
    if ($value::class === $namespace . 'Rows\\With\\WithRead') {
        return ['read' => [$value->definition, array_map(static fn ($column): array => [$column->slot, $column->name->value], $value->columns)]];
    }
    if ($value::class === $namespace . 'Rows\\With\\WithProjection') {
        return ['with' => [
            'definitions' => array_map(static fn ($entry): array => [
                $dialect === 'mysql' ? '' : $entry->materialization->value, observeSetQuery($namespace, $entry->body, $dialect),
            ], $value->definitions),
            'body' => observeSetQuery($namespace, $value->body, $dialect),
        ]];
    }
    if ($value::class === $namespace . 'Rows\\Compound') {
        return ['compound' => [
            'left' => observeSetQuery($namespace, $value->left, $dialect),
            'right' => observeSetQuery($namespace, $value->right, $dialect),
            'operator' => $value->operator->value,
            'all' => $value->all,
        ]];
    }
    if ($value::class === $namespace . 'Rows\\LocalProjection') {
        return ['local' => [
            'definition' => observeSetQuery($namespace, $value->definition, $dialect),
            'columns' => array_map(static fn ($column): array => [$column->slot, $column->name->value], $value->columns),
        ]];
    }
    if ($value::class !== $namespace . 'Select') {
        throw new RuntimeException('Unknown result query role.');
    }
    $source = $value->source;
    return [
        'columns' => array_map(static fn ($column): array => [observeOperand($namespace, $column->expression), $column->name->value], $value->columns),
        'source' => $source === null ? null : observeSource($source, $dialect, $namespace),
        'where' => observePredicate($namespace, $value->where),
        'distinct' => $value->distinct,
        'orderBy' => array_map(static function ($key) use ($dialect, $namespace): array {
            $target = $dialect === 'postgres' ? $key->target : $key->position;
            return [is_int($target) ? $target : observeOperand($namespace, $target), $key->descending, $key->nullsFirst];
        }, $value->orderBy),
        'limit' => $value->limit === null ? null : [$value->limit->count, $value->limit->offset],
    ];
}

function constructGrouped(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $name = $namespace . 'Identifier';
    $output = $namespace . 'Aggregation\\GroupColumn';
    $select = $namespace . 'Aggregation\\GroupedSelect';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $grouped = $input['grouped'];
    $columns = [];
    foreach ($grouped['columns'] as [$expression, $label]) {
        $columns[] = new $output(constructGroupOperand($namespace, $expression), new $name($label));
    }
    return new $select(
        constructSource($platform, $grouped['source'], $input['dialect']),
        array_map(static fn (array $key): object => constructOperand($namespace, $key), $grouped['keys']),
        $columns,
        $release::from($input['release']),
        constructPredicate($namespace, $grouped['where']),
        ...[
            ...($input['dialect'] === 'postgres' ? constructResultOptions($namespace, $grouped, 'postgres') : []),
            constructHaving($namespace, $grouped['having'] ?? null),
        ],
    );
}

function constructGroupOperand(string $namespace, array $term): object
{
    if ($term[0] !== 'aggregate') {
        return constructOperand($namespace, $term);
    }
    $aggregate = $namespace . 'Aggregation\\Aggregate';
    $operator = $namespace . 'Aggregation\\AggregateOperator';

    return new $aggregate($operator::from($term[1]), $term[2] === null ? null : constructOperand($namespace, $term[2]), $term[3]);
}

function constructHaving(string $namespace, ?array $term): ?object
{
    if ($term === null || $term[0] !== 'comparison') {
        return $term === null ? null : constructOperand($namespace, $term);
    }
    $comparison = $namespace . 'Aggregation\\GroupedComparison';
    $operator = $namespace . 'Predicate\\IntegerOperator';

    return new $comparison($operator::from($term[1]), constructGroupOperand($namespace, $term[2]), constructGroupOperand($namespace, $term[3]));
}

function observeGroupOperand(string $namespace, object $value): array
{
    return $value::class === $namespace . 'Aggregation\\Aggregate'
        ? ['aggregate', $value->operator->value, $value->argument === null ? null : observeOperand($namespace, $value->argument), $value->distinct]
        : observeOperand($namespace, $value);
}

function observeHaving(string $namespace, object $value): array
{
    return $value::class === $namespace . 'Aggregation\\GroupedComparison'
        ? ['comparison', $value->operator->value, observeGroupOperand($namespace, $value->left), observeGroupOperand($namespace, $value->right)]
        : observeOperand($namespace, $value);
}

function observeGrouped(string $namespace, object $query, string $dialect): array
{
    $columns = [];
    foreach ($query->columns as $field) {
        $columns[] = [observeGroupOperand($namespace, $field->value), $field->name->value];
    }

    return [
        'status' => 'constructed', 'profile' => $query->profileId(), 'sql' => $query->toString(),
        'grouped' => [
            'source' => observeSource($query->source, $dialect, $namespace), 'columns' => $columns,
            'keys' => array_map(static fn ($key): array => observeOperand($namespace, $key), $query->keys),
            'where' => observePredicate($namespace, $query->where),
            ...($dialect === 'postgres' ? [
                'orderBy' => array_map(static fn ($key): array => [$key->target, $key->descending, $key->nullsFirst], $query->orderBy),
                'limit' => $query->limit === null ? null : [$query->limit->count, $query->limit->offset],
            ] : []),
            ...($query->having === null ? [] : ['having' => observeHaving($namespace, $query->having)]),
        ],
    ];
}

function constructDeclarationColumns(string $namespace, string $dialect, array $definitions): array
{
    $name = $namespace . 'Identifier';
    $column = $namespace . 'Schema\\TableColumn';
    $storage = $namespace . 'Schema\\IntegerStorage';
    $characters = $namespace . 'Schema\\CharacterStorage';
    $boolean = $namespace . 'Schema\\BooleanStorage';
    $collation = $namespace . 'From\\TextCollation';
    $columns = [];
    foreach ($definitions as $index => $definition) {
        if (!is_array($definition)) {
            $columns[$index] = $definition;
            continue;
        }
        [$columnName, $kind, $nullable, $default] = $definition;
        $type = match (true) {
            $kind === 'pg_catalog.text' && $dialect === 'postgres' => $characters::from($kind),
            $kind === 'pg_catalog.bool' && $dialect === 'postgres' => $boolean::from($kind),
            $dialect === 'mysql' && is_array($kind) && $kind[0] === 'varchar' => new $characters($kind[1], $collation::from($kind[2])),
            default => $storage::from($kind),
        };
        $columns[$index] = new $column(new $name($columnName), $type, $nullable, $default === null ? null : constructOperand($namespace, $default));
    }

    return $columns;
}

function constructCreateTable(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Schema\\CreateTable';
    $target = $namespace . 'Schema\\TableName';
    $name = $namespace . 'Identifier';
    $collation = $namespace . 'From\\TextCollation';
    $primary = $namespace . 'Schema\\Key\\PrimaryKey';
    $term = $namespace . 'Schema\\Key\\PrimaryKeyColumn';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $create = $input['create'];
    $columns = constructDeclarationColumns($namespace, $input['dialect'], $create['columns']);
    $coordinate = new $target(new $name($create['target'][0]), new $name($create['target'][1]));
    if ($input['dialect'] === 'mysql') {
        $identity = $namespace . 'Schema\\Identity\\TableIdentity';
        $kind = $namespace . 'From\\ExternalRelationKind';
        $coordinate = new $identity($coordinate, $kind::from($input['createKind'] ?? 'table'));
    }

    return new $statement(
        $coordinate,
        $columns,
        $create['ifNotExists'],
        $release::from($input['release']),
        ...($input['dialect'] === 'mysql' && isset($create['tableCollation'])
            ? ['defaultCollation' => $collation::from($create['tableCollation'])] : []),
        ...($input['dialect'] === 'mysql' && isset($create['primaryKey'])
            ? ['primaryKey' => new $primary(array_map(static fn ($slot) => new $term($slot[0], $slot[1]), $create['primaryKey']))] : []),
    );
}

function constructPostgresValues(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $values = $namespace . 'Rows\\Values\\';
    $statement = $values . 'ValuesQuery';
    $row = $values . 'ValuesRow';
    $cell = $values . 'ValuesCell';
    $release = $platform . 'Model\\Contract\\GrammarRelease';

    return new $statement(
        array_map(static fn (array $cells): object => new $row(array_map(
            static fn (array $operand): object => new $cell(constructOperand($namespace, $operand)), $cells)), $input['postgresValues']),
        $release::from($input['release']),
    );
}

function postgresValuesGraph(string $namespace, object $value): array
{
    $rows = [];
    foreach ($value->rows as $row) {
        $rows[] = array_map(static fn ($cell): array => observeOperand($namespace, $cell->value), $row->cells);
    }
    $kinds = [];
    foreach (array_keys($value->rows[0]->cells) as $position) {
        $kinds[] = $value->columnKind($position)->value;
    }

    return ['postgresValues' => $rows, 'columnKinds' => $kinds];
}

function constructDropTable(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Schema\\DropTable';
    $target = $namespace . 'Schema\\TableName';
    $name = $namespace . 'Identifier';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $drop = $input['drop'];
    $targets = [];
    if ($input['dialect'] === 'postgres') {
        foreach ($drop['targets'] as [$schema, $table]) {
            $targets[] = new $target(new $name($schema), new $name($table));
        }

        return new $statement($targets, $drop['ifExists'], $drop['cascade'], $release::from($input['release']));
    }

    $absent = $namespace . 'Schema\\Identity\\AbsentDropTarget';
    $identity = $namespace . 'Schema\\Identity\\TableIdentity';
    $kind = $namespace . 'From\\ExternalRelationKind';
    foreach ($drop['targets'] as $operand) {
        if (!is_array($operand)) {
            $targets[] = $operand;
            continue;
        }
        [$schema, $table] = $operand;
        $role = $operand[2] ?? 'table';
        $coordinate = new $target(new $name($schema), new $name($table));
        $targets[] = $role === 'absent' ? new $absent($coordinate) : new $identity($coordinate, $kind::from($role));
    }

    return new $statement($targets, $drop['ifExists'], $release::from($input['release']), $drop['temporaryOnly'] ?? false);
}

function observeDropTable(string $namespace, object $value, string $dialect): array
{
    $targets = [];
    $nativeTargets = [];
    foreach ($value->targets as $target) {
        if ($dialect === 'mysql') {
            $coordinate = [$target->name->schema->value, $target->name->table->value];
            $role = match ($target::class) {
                $namespace . 'Schema\\Identity\\AbsentDropTarget' => 'absent',
                $namespace . 'Schema\\Identity\\TableIdentity' => $target->kind->value,
            };
            $nativeTargets[] = [...$coordinate, $role];
        } else {
            $coordinate = [$target->schema->value, $target->table->value];
        }
        $targets[] = $coordinate;
    }
    $drop = ['targets' => $targets, 'ifExists' => $value->ifExists];
    if ($dialect === 'postgres') {
        $drop['cascade'] = $value->cascade;
    }
    return [
        'status' => 'constructed', 'profile' => $value->profileId(), 'drop' => $drop, 'sql' => $value->toString(),
        ...($dialect === 'mysql' ? ['mysqlDrop' => ['targets' => $nativeTargets, 'ifExists' => $value->ifExists,
            'temporaryOnly' => $value->temporaryOnly]] : []),
    ];
}

function constructCreateView(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $view = $namespace . 'Schema\\View\\';
    $statement = $view . 'CreateView';
    $target = $view . 'ViewName';
    $name = $namespace . 'Identifier';
    $column = $view . 'ViewColumn';
    $create = $input['createView'];
    [$schema, $relation] = $create['target'];
    if ($input['dialect'] === 'postgres') {
        return new $statement(
            new $target(new $name($schema), new $name($relation)),
            constructSetQuery($platform, $create['query'] + ['dialect' => 'postgres', 'release' => $input['release']]),
            array_map(static fn (string $value): object => new $name($value), $create['columns']),
            $create['replace'], $create['securityBarrier'], $create['securityInvoker'],
            ($view . 'ViewCheckOption')::from($create['checkOption']),
        );
    }

    return new $statement(
        new $target(new $name($schema), new $name($relation)),
        constructSelect($platform, $create['query'] + ['dialect' => 'mysql', 'release' => $input['release']]),
        array_map(static fn (string $value): object => new $column(new $name($value)), $create['columns']),
        ($view . 'ViewCreationPolicy')::from($create['policy']),
        ($view . 'ViewAlgorithm')::from($create['algorithm']),
        ($view . 'ViewSecurity')::from($create['security']),
    );
}

function constructPostgresMaterialization(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $view = $namespace . 'Schema\\View\\';
    $name = $namespace . 'Identifier';
    $target = $view . 'ViewName';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $action = $input['materialization'];
    [$schema, $relation] = $action['target'];
    $identity = new $target(new $name($schema), new $name($relation));
    if ($action['operation'] === 'refresh') {
        $refresh = $view . 'RefreshMaterializedView';

        return new $refresh($identity, $action['withData'], $release::from($input['release']));
    }
    $create = $view . 'CreateMaterializedView';
    return new $create(
        $identity,
        constructSetQuery($platform, $action['query'] + ['dialect' => 'postgres', 'release' => $input['release']]),
        array_map(static fn (string $value): object => new $name($value), $action['columns']),
        $action['ifNotExists'],
        $action['withData'],
    );
}

function observePostgresMaterialization(string $namespace, object $value): array
{
    $create = $value::class === $namespace . 'Schema\\View\\CreateMaterializedView';
    $target = [$value->name->schema->value, $value->name->view->value];
    return [
        'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'materialization' => [
            'target' => $target, 'operation' => $create ? 'create' : 'refresh', 'withData' => $value->withData,
            ...($create ? ['ifNotExists' => $value->ifNotExists, 'columns' => array_column($value->columns, 'value'),
                'query' => observeSetQuery($namespace, $value->query, 'postgres')] : []),
        ],
    ];
}

function constructPostgresCreateAs(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $table = $namespace . 'Schema\\TableName';
    $statement = $namespace . 'Schema\\Query\\CreateTableAs';
    $name = $namespace . 'Identifier';
    $definition = $input['createTableAs'];
    [$schema, $relation] = $definition['target'];

    return new $statement(
        new $table(new $name($schema), new $name($relation)),
        constructSetQuery($platform, $definition['query'] + ['dialect' => 'postgres', 'release' => $input['release']]),
        array_map(static fn (string $value): object => new $name($value), $definition['columns']),
        $definition['unlogged'], $definition['ifNotExists'], $definition['withData'],
    );
}

function observePostgresCreateAs(string $namespace, object $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'createTableAs' => [
            'target' => [$value->target->schema->value, $value->target->table->value],
            'query' => observeSetQuery($namespace, $value->query, 'postgres'),
            'columns' => array_column($value->columns, 'value'), 'unlogged' => $value->unlogged,
            'ifNotExists' => $value->ifNotExists, 'withData' => $value->withData,
        ],
    ];
}

function constructMysqlRename(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $identifier = $namespace . 'Identifier';
    $name = $namespace . 'Schema\\TableName';
    $member = $namespace . 'Schema\\Alter\\TableRename';
    $statement = $namespace . 'Schema\\Alter\\RenameTable';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $moves = [];
    foreach ($input['tableRename'] as $move) {
        $roles = [];
        foreach (['from', 'to', 'origin'] as $role) {
            [$schema, $table] = $move[$role];
            $roles[] = new $name(new $identifier($schema), new $identifier($table));
        }
        $moves[] = new $member(...$roles);
    }
    return new $statement($moves, $release::from($input['release']));
}

function observeMysqlRename(object $value): array
{
    $moves = [];
    foreach ($value->moves as $move) {
        $coordinates = [];
        foreach (['from', 'to', 'origin'] as $role) {
            $name = $move->{$role};
            $coordinates[$role] = [$name->schema->value, $name->table->value];
        }
        $moves[] = $coordinates;
    }
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(), 'tableRename' => $moves];
}

function constructPostgresRename(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $name = $namespace . 'Identifier';
    $table = $namespace . 'Schema\\TableName';
    $member = $namespace . 'Schema\\Alter\\RenameColumn';
    $relation = $namespace . 'Schema\\Alter\\RenameTable';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $change = $input['rename'];
    [$schema, $source] = $change['target'];
    $target = new $table(new $name($schema), new $name($source));
    $destination = new $name($change['destination']);
    $version = $release::from($input['release']);

    return $change['role'] === 'column'
        ? new $member($target, new $name($change['column']), $destination, $change['only'], $change['ifExists'], $version)
        : new $relation($target, $destination, $change['ifExists'], $version);
}

function observePostgresRename(string $namespace, object $value): array
{
    $member = $value::class === $namespace . 'Schema\\Alter\\RenameColumn';
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'rename' => [
            'role' => $member ? 'column' : 'table', 'target' => [$value->target->schema->value, $value->target->table->value],
            'destination' => $value->destination->value, 'ifExists' => $value->ifExists,
            ...($member ? ['column' => $value->column->value, 'only' => $value->only] : []),
        ],
    ];
}

function mysqlTableIdentity(string $namespace, array $input): object
{
    $identifier = $namespace . 'Identifier';
    $table = $namespace . 'Schema\\TableName';
    $identity = $namespace . 'Schema\\Identity\\TableIdentity';
    $kind = $namespace . 'From\\ExternalRelationKind';
    [$schema, $name, $role] = $input;
    return new $identity(new $table(new $identifier($schema), new $identifier($name)), $kind::from($role));
}

function observeMysqlTableIdentity(object $identity): array
{
    return [$identity->name->schema->value, $identity->name->table->value, $identity->kind->value];
}

function constructMysqlCreateAs(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Schema\\Query\\CreateTableAs';
    $definition = $input['mysqlCreateTableAs'];
    return new $statement(
        mysqlTableIdentity($namespace, $definition['target']),
        constructSelect($platform, $definition['query'] + ['dialect' => 'mysql', 'release' => $input['release']]),
        $definition['ifNotExists'],
        constructDeclarationColumns($namespace, 'mysql', $definition['declaredColumns'] ?? []),
    );
}

function observeMysqlCreateAs(string $namespace, object $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlCreateTableAs' => [
            'target' => observeMysqlTableIdentity($value->target), 'ifNotExists' => $value->ifNotExists,
            'query' => observeSetQuery($namespace, $value->query, 'mysql'),
            ...($value->declaredColumns === [] ? [] : ['declaredColumns' => array_map(
                static fn ($column): array => [$column->name->value, $column->storage->value, $column->nullable,
                    $column->default === null ? null : observeOperand($namespace, $column->default)],
                $value->declaredColumns
            )]),
        ],
    ];
}

function constructMysqlCreateLike(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Schema\\Like\\CreateTableLike';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $copy = $input['createLike'];
    return new $statement(
        mysqlTableIdentity($namespace, $copy['destination']),
        mysqlTableIdentity($namespace, $copy['source']),
        $copy['ifNotExists'],
        $release::from($input['release'])
    );
}

function observeMysqlCreateLike(object $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'createLike' => ['destination' => observeMysqlTableIdentity($value->destination),
            'source' => observeMysqlTableIdentity($value->source), 'ifNotExists' => $value->ifNotExists]];
}

function constructMysqlTruncate(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Schema\\Truncate\\TruncateTable';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    return new $statement(mysqlTableIdentity($namespace, $input['mysqlTruncate']), $release::from($input['release']));
}

function observeMysqlTruncate(object $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlTruncate' => observeMysqlTableIdentity($value->target)];
}

function constructPostgresTruncate(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $identifier = $namespace . 'Identifier';
    $table = $namespace . 'Schema\\TableName';
    $leaf = $namespace . 'Schema\\Truncate\\TruncateTarget';
    $statement = $namespace . 'Schema\\Truncate\\TruncateTable';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $truncate = $input['truncateTable'];
    $targets = [];
    foreach ($truncate['targets'] as $target) {
        if (!is_array($target)) {
            $targets[] = $target;
            continue;
        }
        [$schema, $name, $only] = $target;
        $targets[] = new $leaf(new $table(new $identifier($schema), new $identifier($name)), $only);
    }
    return new $statement($targets, $truncate['restart'], $truncate['cascade'], $release::from($input['release']));
}

function observePostgresTruncate(object $value): array
{
    $targets = [];
    foreach ($value->targets as $target) {
        $targets[] = [$target->table->schema->value, $target->table->table->value, $target->only];
    }
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'truncateTable' => ['targets' => $targets, 'restart' => $value->restartIdentity, 'cascade' => $value->cascade]];
}

function constructPostgresDropIndex(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex
{
    $drop = $input['dropIndex'];
    $targets = [];
    foreach ($drop['targets'] as [$kind, $schema, $index, $detail]) {
        $index = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($index);
        $schema = $schema === null ? null : new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($schema);
        $targets[] = match ($kind) {
            'bound' => new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexReference(
                $schema,
                $index,
                new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($detail),
            ),
            'missing' => new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\MissingIndex($index, $schema, $detail),
        };
    }

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex(
        $targets,
        $drop['ifExists'],
        $drop['cascade'],
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexDropMode::from($drop['mode']),
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
}

function observePostgresDropIndex(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex $drop): array
{
    $targets = [];
    foreach ($drop->targets as $target) {
        $targets[] = match ($target::class) {
            \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexReference::class => ['bound', $target->schema->value, $target->index->value, $target->table->value],
            \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\MissingIndex::class => ['missing', $target->schema?->value, $target->index->value, $target->missingSchema],
        };
    }

    return [
        'status' => 'constructed', 'profile' => $drop->profileId(), 'sql' => $drop->toString(),
        'dropIndex' => ['targets' => $targets, 'ifExists' => $drop->ifExists, 'cascade' => $drop->cascade, 'mode' => $drop->mode->value],
    ];
}

function constructDropView(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Schema\\View\\DropView';
    $target = $namespace . 'Schema\\View\\ViewName';
    $name = $namespace . 'Identifier';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $drop = $input['dropView'];
    $views = array_map(static fn (array $identity): object => new $target(new $name($identity[0]), new $name($identity[1])), $drop['targets']);

    $version = $release::from($input['release']);
    if ($input['dialect'] === 'postgres') {
        $kind = $namespace . 'Schema\\View\\ViewRemovalKind';

        return new $statement($kind::from($drop['kind']), $views, $drop['ifExists'], $drop['cascade'], $version);
    }

    return new $statement($views, $drop['ifExists'], $version);
}

function constructTransaction(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Transaction\\Transaction';
    $command = $namespace . 'Transaction\\TransactionCommand';
    $name = $namespace . 'Identifier';
    $release = $platform . 'Model\\Contract\\GrammarRelease';

    return new $statement($command::from($input['command']), $input['name'] === null ? null : new $name($input['name']), $release::from($input['release']));
}

function constructUpdate(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Mutation\\Update';
    $assignment = $namespace . 'Mutation\\UpdateAssignment';
    $textAssignment = $namespace . 'Mutation\\TextUpdateAssignment';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $target = constructSource($platform, $input['target'], $input['dialect']);
    $sets = [];
    foreach ($input['assignments'] as [$slot, $value]) {
        $role = $input['dialect'] === 'mysql' && $slot[0] === 'text-slot' ? $textAssignment : $assignment;
        $sets[] = new $role(constructOperand($namespace, $slot), constructOperand($namespace, $value));
    }

    return new $statement(
        $target,
        $sets,
        constructPredicate($namespace, $input['where']),
        $release::from($input['release']),
        ...($input['dialect'] === 'postgres' ? [constructReturnedColumns($namespace, $input['returning'] ?? [])] : [])
    );
}

function observeUpdate(string $namespace, object $value, string $dialect): array
{
    $sets = [];
    foreach ($value->assignments as $assignment) {
        $sets[] = [observeOperand($namespace, $assignment->target), observeOperand($namespace, $assignment->value)];
    }

    return [
        'status' => 'constructed', 'profile' => $value->profileId(), 'target' => observeSource($value->target, $dialect, $namespace),
        'assignments' => $sets, 'where' => observePredicate($namespace, $value->where), 'sql' => $value->toString(),
        ...($dialect === 'postgres' ? ['returning' => observeReturnedColumns($namespace, $value->returning)] : []),
    ];
}

function constructInsertQuery(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $type = $namespace . 'Mutation\\InsertQuery';
    $target = constructInsertDestination($platform, $namespace, $input);

    return new $type(
        $target,
        constructSelect($platform, $input['insertQuery']),
        ...($input['dialect'] === 'postgres' ? [constructReturnedColumns($namespace, $input['returning'] ?? [])] : [])
    );
}

function observeInsertQuery(string $namespace, object $value, string $dialect): array
{
    return [
        'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        ...observeInsertDestination($namespace, $value, $dialect),
        'insertQuery' => observeSetQuery($namespace, $value->query, $dialect),
    ];
}

function constructInsert(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Mutation\\Insert';
    $rowClass = $namespace . 'Mutation\\InsertRow';
    $cellClass = $namespace . 'Mutation\\InsertCell';
    $integer = $namespace . 'IntegerLiteral';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $target = constructInsertDestination($platform, $namespace, $input);
    $rows = [];
    foreach ($input['rows'] as $entries) {
        $values = [];
        foreach ($entries as $cell) {
            $values[] = new $cellClass(is_array($cell) ? constructOperand($namespace, $cell) : ($cell === null ? null : new $integer($cell)));
        }
        $rows[] = new $rowClass($values);
    }

    return new $statement(
        $target,
        $rows,
        $release::from($input['release']),
        ...($input['dialect'] === 'postgres' ? [constructReturnedColumns($namespace, $input['returning'] ?? [])] : [])
    );
}

function observeInsert(string $namespace, object $value, string $dialect): array
{
    $rows = [];
    foreach ($value->rows as $row) {
        $rows[] = array_map(static fn ($cell): array|string|null => match (true) {
            $cell->value instanceof SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral,
            $cell->value instanceof SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral => ['text', $cell->value->value],
            $cell->value instanceof SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral => ['boolean', $cell->value->value],
            $cell->value instanceof SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral => ['wide-integer', $cell->value->decimal],
            default => $cell->value?->decimal,
        }, $row->values);
    }

    return [
        'status' => 'constructed', 'profile' => $value->profileId(), ...observeInsertDestination($namespace, $value, $dialect),
        'rows' => $rows, 'sql' => $value->toString(),
    ];
}

function constructInsertDestination(string $platform, string $namespace, array $input): object
{
    $relation = constructSource($platform, $input['target'], $input['dialect']);
    if ($input['dialect'] === 'mysql') {
        return $relation;
    }
    $destination = $namespace . 'Mutation\\Insert\\Destination';
    $slots = $input['written'] ?? array_map(static fn (int $number): array => ['slot', $number], array_keys($input['target']['columns']));

    $writeSlot = $namespace . 'Mutation\\Insert\\WriteSlot';
    $kind = $namespace . 'From\\ColumnKind';

    return new $destination($relation, array_map(
        static fn (array $slot): object => new $writeSlot($slot[1], $kind::from(match ($slot[0]) {
            'slot' => 'int4',
            'text-slot' => 'text',
            'bool-slot' => 'bool',
            'wide-slot' => 'int8',
        }), $slot[2] ?? 0),
        $slots,
    ));
}

function observeInsertDestination(string $namespace, object $value, string $dialect): array
{
    if ($dialect === 'mysql') {
        return ['target' => observeSource($value->target, $dialect, $namespace)];
    }

    return [
        'target' => observeSource($value->target->relation, $dialect, $namespace),
        'written' => array_map(
            static fn ($slot): array => match ($slot->kind->value) {
                'int4' => ['slot', $slot->slot],
                'text' => ['text-slot', $slot->slot, $slot->occurrence],
                'bool' => ['bool-slot', $slot->slot, $slot->occurrence],
                'int8' => ['wide-slot', $slot->slot, $slot->occurrence],
            },
            $value->target->written,
        ),
        'returning' => observeReturnedColumns($namespace, $value->returning),
    ];
}

function constructReturnedColumns(string $namespace, array $fields): array
{
    $field = $namespace . 'Mutation\\ReturnedColumn';
    $identifier = $namespace . 'Identifier';
    $returning = [];
    foreach ($fields as [$reference, $label]) {
        $returning[] = new $field(constructOperand($namespace, $reference), new $identifier($label));
    }

    return $returning;
}

function observeReturnedColumns(string $namespace, array $fields): array
{
    return array_map(static fn ($field): array => [observeOperand($namespace, $field->reference), $field->name->value], $fields);
}

function constructDelete(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $delete = $namespace . 'Mutation\\Delete';
    $where = constructPredicate($namespace, $input['where']);

    return new $delete(
        constructSource($platform, $input['target'], $input['dialect']),
        $where,
        $release::from($input['release']),
        ...($input['dialect'] === 'postgres' ? [constructReturnedColumns($namespace, $input['returning'] ?? [])] : [])
    );
}

function observePredicate(string $namespace, ?object $condition): ?array
{
    if ($condition === null) {
        return null;
    }
    return match ($condition::class) {
        $namespace . 'Predicate\\IntegerComparison' => ['comparison', $condition->operator->value, observeOperand($namespace, $condition->left), observeOperand($namespace, $condition->right)],
        $namespace . 'Predicate\\LogicalPredicate' => ['logic', $condition->operator->value, observePredicate($namespace, $condition->left), observePredicate($namespace, $condition->right)],
        $namespace . 'Predicate\\NullTest' => ['null-test', $condition->negated, observePredicate($namespace, $condition->operand)],
        $namespace . 'BooleanLiteral', $namespace . 'NullLiteral', $namespace . 'ColumnReference', $namespace . 'From\\TextColumnReference', $namespace . 'From\\BooleanColumnReference', $namespace . 'From\\WideIntegerColumnReference', $namespace . 'IntegerLiteral',
        $namespace . 'Predicate\\IntegerRange', $namespace . 'Predicate\\IntegerDistinctness', $namespace . 'Predicate\\Text\\TextComparison', $namespace . 'Predicate\\TruthTest' => observeOperand($namespace, $condition),
    };
}

function observeDelete(string $namespace, object $value, string $dialect): array
{
    return [
        'status' => 'constructed', 'profile' => $value->profileId(),
        'target' => observeSource($value->target, $dialect, $namespace), 'where' => observePredicate($namespace, $value->where), 'sql' => $value->toString(),
        ...($dialect === 'postgres' ? ['returning' => observeReturnedColumns($namespace, $value->returning)] : []),
    ];
}

function observeStatement(array $input): array
{
    $dialect = $input['dialect'];
    $platform = 'SqlSemantics\\Platform\\' . match ($dialect) {
        'mysql' => 'MySql\\',
        'postgres' => 'PostgreSql\\',
    };
    $namespace = $platform . 'Ir\\';
    try {
        $value = match (true) {
            isset($input['sql']) => analyzeStatement($platform, $input),
            isset($input['grouped']) => constructGrouped($platform, $input),
            isset($input['with']) => constructWith($platform, $input),
            isset($input['local']) => constructLocalProjection($platform, $input),
            isset($input['series']) => constructRecursiveSeries($platform, $input),
            isset($input['compound']) => constructSetQuery($platform, $input),
            isset($input['create']) => constructCreateTable($platform, $input),
            isset($input['createLike']) => constructMysqlCreateLike($platform, $input),
            isset($input['mysqlCreateTableAs']) => constructMysqlCreateAs($platform, $input),
            isset($input['drop']) => constructDropTable($platform, $input),
            isset($input['postgresValues']) => constructPostgresValues($platform, $input),
            isset($input['createView']) => constructCreateView($platform, $input),
            isset($input['dropIndex']) => constructPostgresDropIndex($input),
            isset($input['dropView']) => constructDropView($platform, $input),
            isset($input['materialization']) => constructPostgresMaterialization($platform, $input),
            isset($input['createTableAs']) => constructPostgresCreateAs($platform, $input),
            isset($input['rename']) => constructPostgresRename($platform, $input),
            isset($input['tableRename']) => constructMysqlRename($platform, $input),
            isset($input['mysqlTruncate']) => constructMysqlTruncate($platform, $input),
            isset($input['truncateTable']) => constructPostgresTruncate($platform, $input),
            isset($input['command']) => constructTransaction($platform, $input),
            isset($input['target'], $input['assignments']) => constructUpdate($platform, $input),
            isset($input['target'], $input['insertQuery']) => constructInsertQuery($platform, $input),
            isset($input['target'], $input['rows']) => constructInsert($platform, $input),
            isset($input['target']) => constructDelete($platform, $input),
            default => constructSelect($platform, $input),
        };
    } catch (AnalysisException $error) {
        return ['status' => 'rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
    }
    if ($value::class === $namespace . 'Aggregation\\GroupedSelect') {
        return observeGrouped($namespace, $value, $input['dialect']);
    }
    if ($value::class === $namespace . 'Rows\\RecursiveSeries') {
        return [
            'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
            'series' => [
                'start' => observeOperand($namespace, $value->start),
                'increment' => observeOperand($namespace, $value->increment),
                'boundary' => observeOperand($namespace, $value->boundary),
                'comparison' => $value->comparison->value,
                'all' => $value->all,
                'columns' => array_map(static fn ($column): array => [$column->slot, $column->name->value], $value->columns),
            ],
        ];
    }
    if ($dialect === 'mysql' && $value::class === $namespace . 'Schema\\Query\\CreateTableAs') {
        return observeMysqlCreateAs($namespace, $value);
    }
    if ($value::class === $namespace . 'Schema\\Like\\CreateTableLike') {
        return observeMysqlCreateLike($value);
    }
    if ($value::class === $namespace . 'Schema\\CreateTable') {
        $coordinate = $dialect === 'mysql' ? $value->target->name : $value->target;
        $observed = [
            'target' => [$coordinate->schema->value, $coordinate->table->value],
            'ifNotExists' => $value->ifNotExists,
            'columns' => [],
        ];
        if ($dialect === 'mysql' && $value->defaultCollation !== null) {
            $observed['tableCollation'] = $value->defaultCollation->value;
        }
        if ($dialect === 'mysql' && $value->primaryKey !== null) {
            $observed['primaryKey'] = array_map(static fn ($term): array => [$term->slot, $term->descending], $value->primaryKey->terms);
        }
        foreach ($value->columns as $column) {
            $storage = $column->storage;
            $kind = $dialect === 'mysql' && $storage::class === $namespace . 'Schema\\CharacterStorage'
                ? ['varchar', $storage->length, $storage->collation->value] : $storage->value;
            $observed['columns'][] = [
                $column->name->value, $kind, $column->nullable,
                $column->default === null ? null : observeOperand($namespace, $column->default),
            ];
        }

        return ['status' => 'constructed', 'profile' => $value->profileId(), 'create' => $observed, 'sql' => $value->toString(),
            ...($dialect === 'mysql' ? ['createKind' => $value->target->kind->value] : [])];
    }
    if ($value::class === $namespace . 'Schema\\View\\CreateView') {
        if ($dialect === 'postgres') {
            return [
                'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
                'createView' => [
                    'target' => [$value->name->schema->value, $value->name->view->value],
                    'query' => observeSetQuery($namespace, $value->query, $dialect),
                    'columns' => array_column($value->columns, 'value'), 'replace' => $value->replace,
                    'securityBarrier' => $value->securityBarrier, 'securityInvoker' => $value->securityInvoker,
                    'checkOption' => $value->checkOption->value,
                ],
            ];
        }
        return [
            'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
            'createView' => [
                'target' => [$value->name->schema->value, $value->name->view->value],
                'query' => observeSetQuery($namespace, $value->query, $input['dialect']),
                'columns' => array_map(static fn ($column): string => $column->name->value, $value->columns),
                'policy' => $value->policy->value, 'algorithm' => $value->algorithm->value, 'security' => $value->security->value,
            ],
        ];
    }
    if ($value::class === $namespace . 'Schema\\View\\CreateMaterializedView'
        || $value::class === $namespace . 'Schema\\View\\RefreshMaterializedView') {
        return observePostgresMaterialization($namespace, $value);
    }
    if ($dialect === 'postgres' && $value::class === $namespace . 'Schema\\Query\\CreateTableAs') {
        return observePostgresCreateAs($namespace, $value);
    }
    if ($dialect === 'postgres' && in_array($value::class, [$namespace . 'Schema\\Alter\\RenameTable', $namespace . 'Schema\\Alter\\RenameColumn'], true)) {
        return observePostgresRename($namespace, $value);
    }
    if ($dialect === 'mysql' && $value::class === $namespace . 'Schema\\Alter\\RenameTable') {
        return observeMysqlRename($value);
    }
    if ($dialect === 'mysql' && $value::class === $namespace . 'Schema\\Truncate\\TruncateTable') {
        return observeMysqlTruncate($value);
    }
    if ($dialect === 'postgres' && $value::class === $namespace . 'Schema\\Truncate\\TruncateTable') {
        return observePostgresTruncate($value);
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex) {
        return observePostgresDropIndex($value);
    }
    if ($value::class === $namespace . 'Schema\\View\\DropView') {
        return [
            'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
            'dropView' => [
                'targets' => array_map(static fn ($target): array => [$target->schema->value, $target->view->value], $value->targets),
                'ifExists' => $value->ifExists,
                ...($dialect === 'postgres' ? ['kind' => $value->kind->value, 'cascade' => $value->cascade] : []),
            ],
        ];
    }
    if ($value::class === $namespace . 'Schema\\DropTable') {
        return observeDropTable($namespace, $value, $dialect);
    }
    if ($value::class === $namespace . 'Transaction\\Transaction') {
        return [
            'status' => 'constructed', 'profile' => $value->profileId(), 'command' => $value->command->value,
            'name' => $value->name?->value, 'sql' => $value->toString(),
        ];
    }
    if ($value::class === $namespace . 'Mutation\\Update') {
        return observeUpdate($namespace, $value, $input['dialect']);
    }
    if ($value::class === $namespace . 'Mutation\\InsertQuery') {
        return observeInsertQuery($namespace, $value, $input['dialect']);
    }
    if ($value::class === $namespace . 'Mutation\\Insert') {
        return observeInsert($namespace, $value, $input['dialect']);
    }
    if ($value::class === $namespace . 'Mutation\\Delete') {
        return observeDelete($namespace, $value, $input['dialect']);
    }
    return [
        'status' => 'constructed',
        'profile' => $value->profileId(),
        ...observeSetQuery($namespace, $value, $input['dialect']),
        ...($value instanceof SqlSemantics\Platform\MySql\Ir\Rows\Compound ? ['resultKinds' => mysqlSetKinds($value)] : []),
        'sql' => $value->toString(),
    ];
}

echo json_encode(['php' => PHP_VERSION, 'intSize' => PHP_INT_SIZE], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    try {
        $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $response = array_is_list($input) ? array_map(observeStatement(...), $input) : observeStatement($input);
        echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        echo json_encode(['status' => 'error', 'exception' => $error::class, 'message' => $error->getMessage()], JSON_THROW_ON_ERROR) . "\n";
    }
}
