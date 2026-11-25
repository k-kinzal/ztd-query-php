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
        'projected-integer' => 'Literal\\ProjectedIntegerLiteral',
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
        'integral-slot' => 'From\\IntegralColumnReference',
        'operation' => 'Arithmetic',
        'comparison' => 'Predicate\\IntegerComparison',
        'signed-case' => 'Expression\\Choice\\SignedCase',
        'int4-case' => 'Expression\\IntegerCase',
        'builtin-cast' => 'Expression\\BuiltinCast',
        'signed-cast' => 'Expression\\SignedCast',
        'unsigned-cast' => 'Expression\\UnsignedCast',
        'int4-range', 'signed-range' => 'Predicate\\IntegerRange',
        'int4-distinctness' => 'Predicate\\IntegerDistinctness',
        'signed-membership' => 'Predicate\\IntegerMembership',
        'truth-test' => 'Predicate\\TruthTest',
        'mysql-truth-test' => 'Predicate\\Truth\\TruthTest',
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
        'integer', 'projected-integer', 'range-integer', 'wide-integer' => new $type(...array_slice($data, 1)),
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
        'slot', 'text-slot', 'bool-slot', 'wide-slot', 'integral-slot' => new $type($data[1], $data[2] ?? 0),
        'null', 'wide-null' => new $type(),
        'operation' => new $type(
            $operator::from($data[1]),
            constructOperand($namespace, $data[2]),
            constructOperand($namespace, $data[3]),
        ),
        'signed-cast', 'unsigned-cast' => new $type(constructOperand($namespace, $data[1])),
        'builtin-cast' => new $type($castTarget::from($data[1]), constructOperand($namespace, $data[2])),
        'truth-test' => new $type($truth::from($data[1]), constructPredicate($namespace, $data[2])),
        'mysql-truth-test' => new $type(($namespace . 'Predicate\\Truth\\TruthOperator')::from($data[1]), constructPredicate($namespace, $data[2])),
        'null-test' => new $type(constructPredicate($namespace, $data[2]), $data[1]),
        'int4-distinctness' => new $type(
            constructOperand($namespace, $data[2]),
            constructOperand($namespace, $data[3]),
            $data[1],
        ),
        'signed-case' => new $type(
            array_map(static function (array $arm) use ($namespace): object {
                $kind = match ($arm[0]) {
                    'simple' => $namespace . 'Expression\\Choice\\SimpleCaseArm',
                    'searched' => $namespace . 'Expression\\Choice\\SearchedCaseArm',
                };
                return new $kind(constructOperand($namespace, $arm[1]), constructOperand($namespace, $arm[2]));
            }, $data[2]),
            $data[1] === null ? null : constructOperand($namespace, $data[1]),
            $data[3] === null ? null : constructOperand($namespace, $data[3]),
        ),
        'signed-membership' => new $type(
            constructOperand($namespace, $data[2]),
            array_map(static fn (array $item) => constructOperand($namespace, $item), $data[3]),
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
        $namespace . 'Literal\\ProjectedIntegerLiteral' => ['projected-integer', $value->token],
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
        $namespace . 'From\\IntegralColumnReference' => ['integral-slot', $value->slot, $value->occurrence],
        $namespace . 'Predicate\\IntegerComparison' => observePredicate($namespace, $value),
        $namespace . 'Arithmetic' => ['operation', $value->operator->value,
            observeOperand($namespace, $value->left), observeOperand($namespace, $value->right)],
        $namespace . 'Expression\\SignedCast' => ['signed-cast', observeOperand($namespace, $value->operand)],
        $namespace . 'Expression\\UnsignedCast' => ['unsigned-cast', observeOperand($namespace, $value->operand)],
        $namespace . 'Expression\\BuiltinCast' => ['builtin-cast', $value->target->value, observeOperand($namespace, $value->operand)],
        $namespace . 'Predicate\\NullTest' => ['null-test', $value->negated, observePredicate($namespace, $value->operand)],
        $namespace . 'Predicate\\TruthTest' => ['truth-test', $value->operator->value, observePredicate($namespace, $value->operand)],
        $namespace . 'Predicate\\Truth\\TruthTest' => ['mysql-truth-test', $value->operator->value, observePredicate($namespace, $value->operand)],
        $namespace . 'Predicate\\IntegerDistinctness' => ['int4-distinctness', $value->negated,
            observeOperand($namespace, $value->left), observeOperand($namespace, $value->right)],
        $namespace . 'Predicate\\IntegerMembership' => ['signed-membership', $value->negated,
            observeOperand($namespace, $value->subject), array_map(static fn (object $item) => observeOperand($namespace, $item), $value->alternatives)],
        $namespace . 'Predicate\\IntegerRange' => [
            ...($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange
                ? ['int4-range', $value->negated, $value->symmetric] : ['signed-range', $value->negated]),
            observeOperand($namespace, $value->subject), observeOperand($namespace, $value->lower), observeOperand($namespace, $value->upper),
        ],
        $namespace . 'Predicate\\RangeIntegerLiteral' => ['range-integer', $value->decimal, $value->leadingZeroes, $value->negativeZero],
        $namespace . 'Expression\\Choice\\SignedCase' => ['signed-case',
            $value->subject === null ? null : observeOperand($namespace, $value->subject),
            array_map(static fn ($arm): array => [
                $arm instanceof SqlSemantics\Platform\MySql\Ir\Expression\Choice\SimpleCaseArm ? 'simple' : 'searched',
                observeOperand($namespace, $arm->decision), observeOperand($namespace, $arm->result),
            ], $value->arms),
            $value->otherwise === null ? null : observeOperand($namespace, $value->otherwise)],
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
        $selection = constructSelect($platform, $source['derived']);

        return $dialect === 'mysql' ? new $derived($selection, $source['inheritsRequestedAliases'] ?? false) : new $derived($selection);
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
    if ($input[0] === 'signed-integral') {
        return new SqlSemantics\Platform\MySql\Ir\From\IntegralSlot(
            SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::from($input[1]),
            $input[2],
        );
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
        if ($input['dialect'] === 'mysql' && is_array($name)) {
            $label = match ($name[0]) {
                'intrinsic-integer-name' => new SqlSemantics\Platform\MySql\Ir\Literal\IntrinsicIntegerName($name[1]),
                'requested-result-alias' => new SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias($name[1]),
                'requested-result-alias-hex' => new SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias(hex2bin($name[1])),
            };
        } else {
            $label = new $identifier($name);
        }
        $columns[] = new $column(constructOperand($namespace, $expr), $label);
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
    $semantics = isset($input['currentCatalog'])
        ? new $class($input['release'], searchPath: new $searchPath(...$input['schemaPath']),
            currentDatabase: new SqlSemantics\Platform\PostgreSql\Ir\Identifier($input['currentCatalog']))
        : (isset($input['schemaPath'])
        ? new $class($input['release'], searchPath: new $searchPath(...$input['schemaPath']))
        : (isset($input['currentDatabase'])
        ? new $class($input['release'], searchPath: new $searchPath($input['currentDatabase']))
        : new $class($input['release'])));
    return $semantics->analyze($input['sql'], $semantics->context($input['declarations'] ?? null, $input['complete'] ?? true));
}

function observeSource(object $source, string $dialect, string $namespace, bool $exactIntegral = false): array
{
    if ($source::class === $namespace . 'From\\Join') {
        return [
            'kind' => $source->kind->value,
            'left' => observeSource($source->left, $dialect, $namespace, $exactIntegral),
            'right' => observeSource($source->right, $dialect, $namespace, $exactIntegral),
            'on' => observePredicate($namespace, $source->on),
        ];
    }

    if ($source::class === $namespace . 'From\\DerivedSource') {
        $derived = ['derived' => observeSetQuery($namespace, $source->definition, $dialect)];
        if ($dialect === 'mysql' && $source->inheritsRequestedAliases) {
            $derived['inheritsRequestedAliases'] = true;
        }

        return $derived;
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
            static fn ($type): array|string => match ($type::class) {
                SqlSemantics\Platform\MySql\Ir\From\Varchar::class =>
                    ['varchar', $type->length, $type->collation->value, ...($type->nullable ? [] : [false])],
                SqlSemantics\Platform\MySql\Ir\From\IntegralSlot::class =>
                    ['signed-integral', $type->storage->value, $type->nullable],
                SqlSemantics\Platform\MySql\Ir\From\ColumnKind::class => $exactIntegral ? $type->value : 'int',
            },
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
        'boolean', 'null', 'slot', 'text-slot', 'bool-slot', 'wide-slot', 'integral-slot', 'integer', 'range-integer', 'text-comparison', 'int4-range', 'signed-range', 'int4-distinctness', 'signed-membership', 'truth-test', 'mysql-truth-test' => constructOperand($namespace, $test),
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

    return ($input['dialect'] ?? '') === 'mysql'
        ? new $statement($source, $columns, $local['inheritsRequestedAliases'] ?? false)
        : new $statement($source, $columns);
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

function observeSetQuery(string $namespace, object $value, string $dialect, bool $exactIntegral = false): array
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
        $local = [
            'definition' => observeSetQuery($namespace, $value->definition, $dialect),
            'columns' => array_map(static fn ($column): array => [$column->slot, $column->name->value], $value->columns),
        ];
        if ($dialect === 'mysql' && $value->inheritsRequestedAliases) {
            $local['inheritsRequestedAliases'] = true;
        }

        return ['local' => $local];
    }
    if ($value::class !== $namespace . 'Select') {
        throw new RuntimeException('Unknown result query role.');
    }
    $source = $value->source;
    return [
        'columns' => array_map(static fn ($column): array => [
            observeOperand($namespace, $column->expression),
            match (true) {
                $column->name instanceof SqlSemantics\Platform\MySql\Ir\Literal\IntrinsicIntegerName =>
                    ['intrinsic-integer-name', $column->name->value],
                $column->name instanceof SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias =>
                    ['requested-result-alias', $column->name->requested, $column->name->value],
                default => $column->name->value,
            },
        ], $value->columns),
        'source' => $source === null ? null : observeSource($source, $dialect, $namespace, $exactIntegral),
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
        array_map(static fn (array $key): object => constructGroupOperand($namespace, $key), $grouped['keys']),
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
    if ($term[0] === 'group-name') {
        $reference = $namespace . 'Aggregation\\GroupNameReference';
        $identifier = $namespace . 'Identifier';
        return new $reference(constructOperand($namespace, $term[1]), new $identifier($term[2]));
    }
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
    if ($value::class === $namespace . 'Aggregation\\GroupNameReference') {
        return ['group-name', observeOperand($namespace, $value->column), $value->name->value];
    }
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
            'keys' => array_map(static fn ($key): array => observeGroupOperand($namespace, $key), $query->keys),
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
        if ($dialect === 'postgres' && $definition === 'foreign-object') {
            $columns[$index] = new stdClass();
            continue;
        }
        [$columnName, $kind, $nullable, $default] = $definition;
        $type = match (true) {
            $kind === 'pg_catalog.text' && $dialect === 'postgres' => $characters::from($kind),
            $kind === 'pg_catalog.bool' && $dialect === 'postgres' => $boolean::from($kind),
            $dialect === 'mysql' && is_array($kind) && $kind[0] === 'varchar' => new $characters($kind[1], $collation::from($kind[2])),
            default => $storage::from($kind),
        };
        $columns[$index] = new $column(
            new $name($columnName), $type, $nullable, $default === null ? null : constructOperand($namespace, $default),
            ...($dialect === 'postgres' && isset($definition[4])
                ? ['collation' => \SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation::from($definition[4])] : []),
        );
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
        ...($input['dialect'] === 'postgres' && isset($create['primaryKey'])
            ? ['primaryKey' => constructPostgresPrimaryKey($create['primaryKey'])] : []),
        ...($input['dialect'] === 'postgres' && isset($create['uniqueKeys'])
            ? ['uniqueKeys' => array_map(constructPostgresUniqueKey(...), $create['uniqueKeys'])] : []),
    );
}

function constructPostgresPrimaryKey(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKey
{
    $identifier = static fn ($field) => is_string($field) ? new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($field) : $field;
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKey(
        array_map($identifier, $input['keys']),
        array_map($identifier, $input['included']),
        $input['name'] === null ? null : $identifier($input['name']),
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyTiming::from($input['timing']),
    );
}

function constructPostgresUniqueKey(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\UniqueKey
{
    $identifier = static fn ($field) => is_string($field) ? new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($field) : $field;
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\UniqueKey(
        array_map($identifier, $input['keys']),
        array_map($identifier, $input['included']),
        $input['name'] === null ? null : $identifier($input['name']),
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyTiming::from($input['timing']),
        $input['nullsNotDistinct'],
    );
}

function observePostgresUniqueKey(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\UniqueKey $key): array
{
    return [
        'keys' => array_column($key->keys, 'value'), 'included' => array_column($key->included, 'value'),
        'name' => $key->name?->value, 'timing' => $key->timing->value, 'nullsNotDistinct' => $key->nullsNotDistinct,
    ];
}

function observePostgresPrimaryKey(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKey $key): array
{
    return [
        'keys' => array_column($key->keys, 'value'), 'included' => array_column($key->included, 'value'),
        'name' => $key->name?->value, 'timing' => $key->timing->value,
    ];
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

    $references = array_map(static fn (array $edge) => new SqlSemantics\Platform\MySql\Ir\Schema\DropDependency\TableDropReference(
        mysqlTableIdentity($namespace, $edge[0]), mysqlTableIdentity($namespace, $edge[1])
    ), $drop['references'] ?? []);

    return new $statement($targets, $references, $drop['ifExists'], $release::from($input['release']), $drop['temporaryOnly'] ?? false);
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
        array_map(static fn (string|array $value): object => new $column(is_string($value) ? new $name($value) : match ($value[0]) {
            'requested-result-alias' => new SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias($value[1]),
        }), $create['columns']),
        ($view . 'ViewCreationPolicy')::from($create['policy']),
        ($view . 'ViewAlgorithm')::from($create['algorithm']),
        ($view . 'ViewSecurity')::from($create['security']),
    );
}

function observePostgresCreateView(\SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView $value): array
{
    return [
        'target' => [$value->name->schema->value, $value->name->view->value],
        'query' => observeSetQuery('SqlSemantics\\Platform\\PostgreSql\\Ir\\', $value->query, 'postgres'),
        'columns' => array_column($value->columns, 'value'), 'replace' => $value->replace,
        'securityBarrier' => $value->securityBarrier, 'securityInvoker' => $value->securityInvoker,
        'checkOption' => $value->checkOption->value,
    ];
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

function constructMysqlIndexPolicy(array $options): SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy
{
    return new SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy(
        SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm::from($options[0]),
        SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexLock::from($options[1])
    );
}

function constructMysqlIndexIdentity(array $spec): SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity
{
    return new SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity(
        mysqlTableIdentity('SqlSemantics\\Platform\\MySql\\Ir\\', $spec['owner']),
        new SqlSemantics\Platform\MySql\Ir\Identifier($spec['name']),
        SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole::from($spec['role'])
    );
}

function constructMysqlIndexRemovalTarget(array $spec): SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget
{
    $source = $spec['autoIncrement'];
    $increment = $source === null ? null : new SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexAutoIncrement(
        new SqlSemantics\Platform\MySql\Ir\Identifier($source['field']),
        array_map(static fn (string $name): SqlSemantics\Platform\MySql\Ir\Identifier => new SqlSemantics\Platform\MySql\Ir\Identifier($name), $source['alternates'])
    );

    return new SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget(
        constructMysqlIndexIdentity($spec), $increment
    );
}

/** @return list<SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement> */
function constructMysqlForeignKeyRequirements(array $input): array
{
    $identifier = SqlSemantics\Platform\MySql\Ir\Identifier::class;
    return array_map(static fn (array $requirement): SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement =>
        new SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement(
            mysqlTableIdentity('SqlSemantics\\Platform\\MySql\\Ir\\', $requirement['owner']),
            array_map(static fn (string $key): object => new $identifier($key), $requirement['keys'])
        ), $input['mysqlIndexForeignKeys'] ?? []);
}

function constructMysqlDropIndex(array $input): SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex
{
    $spec = $input['mysqlDropIndex'];

    return new SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex(
        constructMysqlIndexRemovalTarget($spec), constructMysqlForeignKeyRequirements($input), SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::from($input['release']),
        constructMysqlIndexPolicy($spec['policy']), $spec['ignore']
    );
}

function constructMysqlDropIndexes(array $input): SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexes
{
    $spec = $input['mysqlDropIndexes'];

    return new SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexes(
        array_map(constructMysqlIndexRemovalTarget(...), $spec['targets']), constructMysqlForeignKeyRequirements($input),
        SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::from($input['release']),
        constructMysqlIndexPolicy($spec['policy']), $spec['ignore']
    );
}

function constructMysqlIndexVisibility(array $input): SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SetIndexVisibility
{
    $spec = $input['mysqlIndexVisibility'];
    $edits = array_map(static fn (array $edit): SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\IndexVisibilityEdit =>
        new SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\IndexVisibilityEdit(
            new SqlSemantics\Platform\MySql\Ir\Identifier($edit[0]),
            SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SecondaryIndexKind::from($edit[1]),
            $edit[2]
        ), $spec['edits']);

    return new SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SetIndexVisibility(
        mysqlTableIdentity('SqlSemantics\\Platform\\MySql\\Ir\\', $spec['owner']), $edits,
        SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::from($input['release']),
        constructMysqlIndexPolicy($spec['policy'])
    );
}

function observeMysqlIndexVisibility(SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SetIndexVisibility $value): array
{
    $edits = array_map(static fn (object $edit): array => [$edit->index->value, $edit->kind->value, $edit->visible], $value->edits);

    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlIndexVisibility' => ['owner' => observeMysqlTableIdentity($value->owner), 'edits' => $edits,
            'policy' => [$value->policy->algorithm->value, $value->policy->lock->value]]];
}

function constructMysqlIndexRenames(array $input): SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\RenameIndexes
{
    $spec = $input['mysqlIndexRenames'];
    $name = SqlSemantics\Platform\MySql\Ir\Identifier::class;
    $moves = array_map(static fn (array $move): SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename =>
        new SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename(new $name($move[0]), new $name($move[1])), $spec['moves']);

    return new SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\RenameIndexes(
        mysqlTableIdentity('SqlSemantics\\Platform\\MySql\\Ir\\', $spec['owner']), $moves,
        array_map(static fn (string $unmoved): SqlSemantics\Platform\MySql\Ir\Identifier => new $name($unmoved), $spec['unmoved']),
        SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::from($input['release']),
        constructMysqlIndexPolicy($spec['policy'])
    );
}

function observeMysqlIndexRenames(SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\RenameIndexes $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlIndexRenames' => ['owner' => observeMysqlTableIdentity($value->owner),
            'moves' => array_map(static fn (object $move): array => [$move->source->value, $move->destination->value], $value->moves),
            'policy' => [$value->policy->algorithm->value, $value->policy->lock->value]]];
}

function constructMysqlMixedIndexes(array $input): SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes
{
    $spec = $input['mysqlMixedIndexes'];
    $name = SqlSemantics\Platform\MySql\Ir\Identifier::class;
    $actions = $bindings = [];
    foreach ($spec['actions'] as $action) {
        if (isset($action['drop'])) {
            $bound = constructMysqlIndexRemovalTarget($action['drop']);
            $bindings[] = $bound;
            $operation = constructMysqlIndexIdentity($action['drop']);
        } else {
            $operation = new SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename(
                new $name($action['rename'][0]), new $name($action['rename'][1])
            );
        }
        $actions[] = new SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\IndexAlteration($operation);
    }

    return new SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes(
        mysqlTableIdentity('SqlSemantics\\Platform\\MySql\\Ir\\', $spec['owner']), $actions, $bindings,
        array_map(static fn (string $unmoved): SqlSemantics\Platform\MySql\Ir\Identifier => new $name($unmoved), $spec['unmoved']),
        constructMysqlForeignKeyRequirements($input),
        SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::from($input['release']),
        constructMysqlIndexPolicy($spec['policy'])
    );
}

function observeMysqlMixedIndexes(SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes $value): array
{
    $actions = [];
    foreach ($value->actions as $action) {
        $change = $action->operation;
        $actions[] = $change instanceof SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity
            ? ['drop' => observeMysqlIndexIdentity($change)] : ['rename' => [$change->source->value, $change->destination->value]];
    }

    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlMixedIndexes' => ['owner' => observeMysqlTableIdentity($value->owner), 'actions' => $actions,
            'policy' => [$value->policy->algorithm->value, $value->policy->lock->value]]];
}

function constructMysqlForeignKeyString(array $value, string $types): object
{
    $storage = $types . 'ForeignKeyStringStorage';
    $kind = $types . 'ForeignKeyStringKind';
    $collation = $types . 'ForeignKeyTextCollation';

    return new $storage($kind::from($value[0]), $value[1], $value[2] === null ? null : $collation::from($value[2]));
}

function constructMysqlForeignKeyPair(array $field, string $namespace): object
{
    $addition = $namespace . 'Schema\\ForeignKey\\Add\\';
    $types = $addition . 'Domain\\';
    $pair = $addition . 'ForeignKeyPair';
    $name = $namespace . 'Identifier';
    if ($field[2] === 'string') {
        $comparison = $types . 'ForeignKeyStringDomain';
        $domain = new $comparison(constructMysqlForeignKeyString($field[3], $types), constructMysqlForeignKeyString($field[4], $types));

        return new $pair(new $name($field[0]), new $name($field[1]), $domain, $field[5], $field[6]);
    }
    if ($field[2] === 'scalar') {
        $comparison = $types . 'ForeignKeyScalarDomain';
        $domain = new $comparison(constructMysqlForeignKeySide($field[3], $namespace), constructMysqlForeignKeySide($field[4], $namespace));

        return new $pair(new $name($field[0]), new $name($field[1]), $domain, $field[5], $field[6]);
    }
    if ($field[2] === 'enumeration') {
        $comparison = $addition . 'Enumeration\\ForeignKeyEnumerationDomain';
        $domain = new $comparison(constructMysqlForeignKeySide($field[3], $namespace), constructMysqlForeignKeySide($field[4], $namespace));

        return new $pair(new $name($field[0]), new $name($field[1]), $domain, $field[5], $field[6]);
    }
    $storage = $namespace . 'Schema\\IntegerStorage';
    $comparison = $types . 'ForeignKeyIntegerDomain';
    $domain = new $comparison($storage::from($field[2]), $field[3]);

    return new $pair(new $name($field[0]), new $name($field[1]), $domain, $field[4], $field[5]);
}

function constructMysqlForeignKeySide(array $value, string $namespace): object
{
    $types = $namespace . 'Schema\\ForeignKey\\Add\\Domain\\';
    $integer = $types . 'ForeignKeyIntegerDomain';
    $integerWidth = $namespace . 'Schema\\IntegerStorage';
    $scalar = $types . 'ForeignKeyScalarStorage';
    $scalarKind = $types . 'ForeignKeyScalarKind';

    return match ($value[0]) {
        'integer' => new $integer($integerWidth::from($value[1]), $value[2]),
        'string' => constructMysqlForeignKeyString(array_slice($value, 1), $types),
        'scalar' => new $scalar($scalarKind::from($value[1]), $value[2], $value[3], $value[4], $value[5]),
        'enumeration' => constructMysqlForeignKeyEnumeration($value, $namespace),
    };
}

function constructMysqlForeignKeyEnumeration(array $value, string $namespace): object
{
    $types = $namespace . 'Schema\\ForeignKey\\Add\\Enumeration\\';
    $storage = $types . 'ForeignKeyEnumerationStorage';
    $kind = $types . 'ForeignKeyEnumerationKind';
    $member = $types . 'ForeignKeyEnumerationMember';
    $collation = $namespace . 'Schema\\ForeignKey\\Add\\Domain\\ForeignKeyTextCollation';

    return new $storage($kind::from($value[1]), array_map(static fn (string $label): object => new $member($label), $value[3]),
        $collation::from($value[2]));
}

function observeMysqlForeignKeyPair(
    SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyPair $field
): array {
    $domain = $field->domain;
    $sides = [$field->child->value, $field->parent->value];
    if ($domain instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringDomain) {
        $child = [$domain->child->kind->value, $domain->child->length, $domain->child->collation?->value];
        $parent = [$domain->parent->kind->value, $domain->parent->length, $domain->parent->collation?->value];

        return [...$sides, 'string', $child, $parent, $field->nullable, $field->descending];
    }
    if ($domain instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarDomain) {
        return [...$sides, 'scalar', observeMysqlForeignKeySide($domain->child), observeMysqlForeignKeySide($domain->parent),
            $field->nullable, $field->descending];
    }
    if ($domain instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationDomain) {
        return [...$sides, 'enumeration', observeMysqlForeignKeySide($domain->child), observeMysqlForeignKeySide($domain->parent),
            $field->nullable, $field->descending];
    }

    return [...$sides, $domain->storage->value, $domain->unsigned, $field->nullable, $field->descending];
}

function observeMysqlForeignKeySide(
    SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain
        |SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringStorage
        |SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage
        |SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationStorage $side
): array {
    return match (true) {
        $side instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain =>
            ['integer', $side->storage->value, $side->unsigned],
        $side instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringStorage =>
            ['string', $side->kind->value, $side->length, $side->collation?->value],
        $side instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage =>
            ['scalar', $side->kind->value, $side->precision, $side->scale, $side->unsigned, $side->zerofill],
        $side instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationStorage =>
            ['enumeration', $side->kind->value, $side->collation->value, array_column($side->members, 'value')],
    };
}

function constructMysqlForeignKeyAddition(array $given, string $namespace): SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition
{
    $addition = $namespace . 'Schema\\ForeignKey\\Add\\';
    $change = $addition . 'ForeignKeyAddition';
    $effects = $addition . 'ForeignKeyEffects';
    $action = $addition . 'ForeignKeyAction';
    $match = $addition . 'ForeignKeyMatch';
    $name = $namespace . 'Identifier';
    $fields = array_map(static fn (array $field): object => constructMysqlForeignKeyPair($field, $namespace), $given['pairs']);
    [$update, $delete, $mode] = $given['effects'];
    return new $change(
        mysqlTableIdentity($namespace, $given['parent']), new $name($given['symbol']), $fields,
        new $effects($action::from($update), $action::from($delete), $match::from($mode)), $given['generated'],
        $given['indexName'] === null ? null : new $name($given['indexName']),
        $given['created'] === null ? null : new $name($given['created'])
    );
}

function constructMysqlForeignKeyCatalog(array $facts, string $namespace): array
{
    $catalog = $namespace . 'Schema\\ForeignKey\\Add\\Catalog\\';
    $index = $catalog . 'ForeignKeyNativeKey';
    $column = $catalog . 'ForeignKeyKeyColumn';
    $constraint = $catalog . 'ForeignKeyConstraintIdentity';
    $name = $namespace . 'Identifier';
    $limit = SqlSemantics\Platform\MySql\Model\Contract\InnoDbKeyLimit::class;
    $keys = array_map(static fn (array $key): object => new $index(constructMysqlIndexIdentity($key),
        array_map(static fn (array $field): object => new $column(new $name($field[0]), $field[1]), $key['fields']), $key['full'],
        $key['referenceKey'] ?? true, $key['implicitForeignKey'] ?? false, $key['clusterEligible'] ?? false), $facts['keys']);
    $constraints = array_map(static fn (array $stored): object =>
        new $constraint(mysqlTableIdentity($namespace, $stored[0]), new $name($stored[1])), $facts['constraints']);

    return [$keys, $constraints, isset($facts['keyLimit']) ? $limit::from($facts['keyLimit']) : null];
}

function constructMysqlAddForeignKeys(array $input): SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\AddForeignKeys
{
    $namespace = 'SqlSemantics\\Platform\\MySql\\Ir\\';
    $root = $namespace . 'Schema\\ForeignKey\\Add\\AddForeignKeys';
    $spec = $input['mysqlAddForeignKeys'];
    $additions = [];
    foreach ($spec['additions'] as $position => $given) {
        $additions[$position] = constructMysqlForeignKeyAddition($given, $namespace);
    }
    [$keys, $constraints, $limit] = constructMysqlForeignKeyCatalog($input['mysqlForeignKeyCatalog'], $namespace);

    return new $root(mysqlTableIdentity($namespace, $spec['owner']), $additions, $keys, $constraints,
        SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::from($input['release']),
        constructMysqlIndexPolicy($spec['policy']), $spec['ignore'], $limit);
}

function observeMysqlForeignKeyAddition(SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition $addition): array
{
    return ['parent' => observeMysqlTableIdentity($addition->parent),
        'symbol' => $addition->symbol->value, 'generated' => $addition->generated,
        'pairs' => array_map(observeMysqlForeignKeyPair(...), $addition->pairs),
        'effects' => [$addition->effects->update->value, $addition->effects->delete->value, $addition->effects->match->value],
        'created' => $addition->createdIndex?->value, 'indexName' => $addition->indexName?->value];
}

function observeMysqlAddForeignKeys(SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\AddForeignKeys $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlAddForeignKeys' => ['owner' => observeMysqlTableIdentity($value->owner),
            'additions' => array_map(observeMysqlForeignKeyAddition(...), $value->additions),
            'removedIndexes' => array_map(static fn ($name): string => $name->value, $value->removedIndexes),
            'policy' => [$value->policy->algorithm->value, $value->policy->lock->value], 'ignore' => $value->ignore]];
}

function constructMysqlMixedIndexAddition(array $item, string $namespace): SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\IndexAddition
{
    $name = $namespace . 'Identifier';
    $source = $namespace . 'TableSource';
    $kind = $namespace . 'From\\ExternalRelationKind';
    $column = $namespace . 'From\\ColumnKind';
    $ordered = $item['heading'] ?? array_map(static fn (array $field): array => [$field[0], 'int'], $item['fields']);
    $owner = $item['headingOwner'] ?? $item['owner'];
    [$keys] = constructMysqlForeignKeyCatalog(['keys' => [$item + ['full' => true, 'autoIncrement' => null]], 'constraints' => []], $namespace);
    $heading = new $source(new $name($owner[0]), new $name($owner[1]),
        array_map(static fn (array $field): object => new $name($field[0]), $ordered),
        array_map(static fn (array $field): object => $column::from($field[1]), $ordered), $kind::from($owner[2]));

    return new SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\IndexAddition($keys[0], $heading, $item['automatic']);
}

function observeMysqlMixedIndexAddition(SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\IndexAddition $value): array
{
    $identity = $value->key->identity;

    return ['owner' => observeMysqlTableIdentity($identity->owner), 'name' => $identity->name->value,
        'role' => $identity->role->value, 'automatic' => $value->automatic,
        'fields' => array_map(static fn ($field): array => [$field->field->value, $field->descending], $value->key->fields)];
}

function constructMysqlAlterForeignKeys(array $input): SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\AlterForeignKeys
{
    $namespace = 'SqlSemantics\\Platform\\MySql\\Ir\\';
    $spec = $input['mysqlAlterForeignKeys'];
    $edits = [];
    foreach ($spec['actions'] as $position => $edit) {
        $edits[$position] = match (true) {
            isset($edit['add']) => constructMysqlForeignKeyAddition($edit['add'], $namespace),
            isset($edit['index']) => constructMysqlMixedIndexAddition($edit['index'], $namespace),
            isset($edit['physicalDrop']) => constructMysqlIndexRemovalTarget($edit['physicalDrop']),
            isset($edit['drop']) => new SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval(
                $edit['drop'][0] === null ? null : new SqlSemantics\Platform\MySql\Ir\Identifier($edit['drop'][0]),
                SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind::from($edit['drop'][1]),
            ),
            default => $edit,
        };
    }
    [$keys, $constraints, $limit] = constructMysqlForeignKeyCatalog($input['mysqlForeignKeyCatalog'], $namespace);
    $name = $namespace . 'Identifier';
    $sides = isset($spec['remainingSides']) ? array_map(
        static fn (array $side): SqlSemantics\Platform\MySql\Model\Statement\Declaration\ForeignKeyIndexSide =>
            new SqlSemantics\Platform\MySql\Model\Statement\Declaration\ForeignKeyIndexSide(
                new SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity(
                    mysqlTableIdentity($namespace, $side[2]), new $name($side[3])
                ),
                array_map(static fn (string $field): object => new $name($field), $side[1]), $side[0]
            ),
        $spec['remainingSides'],
    ) : null;

    return new SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\AlterForeignKeys(
        mysqlTableIdentity($namespace, $spec['owner']), $edits, $keys, $constraints,
        SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::from($input['release']),
        constructMysqlIndexPolicy($spec['policy']), $spec['ignore'], $limit, $sides);
}

function observeMysqlAlterForeignKeys(SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\AlterForeignKeys $value): array
{
    $actions = [];
    $physical = false;
    foreach ($value->actions as $edit) {
        $actions[] = match (true) {
            $edit instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval =>
                ['drop' => [$edit->name?->value, $edit->kind->value]],
            $edit instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\IndexAddition =>
                ['index' => observeMysqlMixedIndexAddition($edit)],
            $edit instanceof SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity =>
                ['physicalDrop' => observeMysqlIndexIdentity($edit)],
            $edit instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition =>
                ['add' => observeMysqlForeignKeyAddition($edit)],
        };
        $physical = $physical || $edit instanceof SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
    }

    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlAlterForeignKeys' => ['owner' => observeMysqlTableIdentity($value->owner), 'actions' => $actions,
            'removedIndexes' => array_map(static fn ($name): string => $name->value, $value->removedIndexes),
            'policy' => [$value->policy->algorithm->value, $value->policy->lock->value], 'ignore' => $value->ignore,
            ...($physical ? ['clusteredReplacement' => $value->clusteredReplacement === null
                ? null : observeMysqlIndexIdentity($value->clusteredReplacement)] : [])]];
}

function constructMysqlDropForeignKeys(array $input): SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\DropForeignKeys
{
    $spec = $input['mysqlDropForeignKeys'];
    $actions = [];
    foreach ($spec['actions'] as $position => $action) {
        $actions[$position] = match (true) {
            isset($action['index']) => constructMysqlIndexRemovalTarget($action['index']),
            isset($action['foreign']) => new SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval(
                $action['foreign'][0] === null ? null : new SqlSemantics\Platform\MySql\Ir\Identifier($action['foreign'][0]),
                SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind::from($action['foreign'][1])
            ),
            default => $action,
        };
    }

    return new SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\DropForeignKeys(
        mysqlTableIdentity('SqlSemantics\\Platform\\MySql\\Ir\\', $spec['owner']), $actions, constructMysqlForeignKeyRequirements($input),
        SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease::from($input['release']), constructMysqlIndexPolicy($spec['policy']), $spec['ignore']
    );
}

function observeMysqlDropForeignKeys(SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\DropForeignKeys $value): array
{
    $actions = [];
    foreach ($value->actions as $action) {
        $actions[] = $action instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval
            ? ['foreign' => [$action->name?->value, $action->kind->value]] : ['index' => observeMysqlIndexIdentity($action)];
    }

    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlDropForeignKeys' => ['owner' => observeMysqlTableIdentity($value->owner), 'actions' => $actions,
            'policy' => [$value->policy->algorithm->value, $value->policy->lock->value], 'ignore' => $value->ignore]];
}

function observeMysqlIndexIdentity(SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity $target): array
{
    return ['owner' => observeMysqlTableIdentity($target->owner), 'name' => $target->name->value, 'role' => $target->role->value];
}

function observeMysqlDropIndex(SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlDropIndex' => [...observeMysqlIndexIdentity($value->target),
            'policy' => [$value->policy->algorithm->value, $value->policy->lock->value], 'ignore' => $value->ignore]];
}

function observeMysqlDropIndexes(SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexes $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlDropIndexes' => ['targets' => array_map(observeMysqlIndexIdentity(...), $value->targets),
            'policy' => [$value->policy->algorithm->value, $value->policy->lock->value], 'ignore' => $value->ignore]];
}

function constructMysqlCreateIndex(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $index = $namespace . 'Schema\\Index\\CreateIndex';
    $column = $namespace . 'Schema\\Index\\IndexColumn';
    $name = $namespace . 'Identifier';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $definition = $input['mysqlCreateIndex'];
    $target = constructSource($platform, $definition['target'], 'mysql');
    $parts = [];
    foreach ($definition['columns'] as $position => $reference) {
        $parts[$position] = is_array($reference) ? new $column($reference[0], $reference[1]) : $reference;
    }

    return new $index($target, new $name($definition['name']), $parts, $definition['unique'], $definition['visible'],
        $release::from($input['release']), $definition['ownerHasIdentityKey'],
        array_map(static fn (string $key): object => new $name($key), $definition['priorKeys']),
        constructMysqlIndexPolicy($definition['policy']));
}

function observeMysqlCreateIndex(string $namespace, SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndex $value): array
{
    $target = observeSource($value->target, 'mysql', $namespace);
    $target['columnTypes'] = array_map(
        static fn ($kind): string|array => $kind instanceof SqlSemantics\Platform\MySql\Ir\From\Varchar
            ? ['varchar', $kind->length, $kind->collation->value, ...($kind->nullable ? [] : [false])] : $kind->value,
        $value->target->columnTypes
    );

    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'mysqlCreateIndex' => [
            'target' => $target, 'name' => $value->name->value, 'role' => $value->role->value, 'visible' => $value->visible,
            'policy' => [$value->policy->algorithm->value, $value->policy->lock->value],
            'columns' => array_map(static fn ($column): array => [$column->slot, $column->descending], $value->columns),
        ],
    ];
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
        'mysqlInputNullability' => $value->query->source instanceof SqlSemantics\Platform\MySql\Ir\TableSource
            ? array_map(static fn ($type): bool => $type instanceof SqlSemantics\Platform\MySql\Ir\From\Varchar
                ? $type->nullable : $type->nullable(), $value->query->source->columnTypes) : [],
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
    $target = $input['mysqlTruncate'] === null ? null : mysqlTableIdentity($namespace, $input['mysqlTruncate']);
    $referrers = array_map(static fn (array $referrer) => mysqlTableIdentity($namespace, $referrer), $input['referrers']);

    return new $statement($target, $referrers, $release::from($input['release']));
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

function constructPostgresIndexPredicate(?array $predicate): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexTruthTest|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanColumn|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanPredicate|null
{
    if ($predicate === null) {
        return null;
    }
    if (is_int($predicate[0])) {
        if ($predicate[1] === 'column') {
            return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanColumn($predicate[0]);
        }
        if (is_string($predicate[1])) {
            return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexTruthTest(
                $predicate[0], \SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator::from($predicate[1]),
            );
        }
        return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate($predicate[0], $predicate[1]);
    }

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanPredicate(
        \SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator::from($predicate[0]),
        constructPostgresIndexPredicate($predicate[1]),
        constructPostgresIndexPredicate($predicate[2]),
    );
}

function observePostgresIndexPredicate(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexTruthTest|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanColumn|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanPredicate|null $predicate): ?array
{
    if ($predicate === null) {
        return null;
    }
    if ($predicate instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate) {
        return [$predicate->slot, $predicate->negated];
    }
    if ($predicate instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexTruthTest) {
        return [$predicate->slot, $predicate->operator->value];
    }
    if ($predicate instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanColumn) {
        return [$predicate->slot, 'column'];
    }

    return [
        $predicate->operator->value,
        observePostgresIndexPredicate($predicate->left),
        observePostgresIndexPredicate($predicate->right),
    ];
}

function constructPostgresBtree(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex
{
    $value = $input['createBtree'];
    [$schema, $table, $names, $kinds, $only] = $value['table'];
    $identifier = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
    $target = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget(new $identifier($schema), new $identifier($table), $only);
    $owner = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading(
        $target,
        array_map(static fn (string $column) => new $identifier($column), $names),
        array_map(static fn (?string $kind) => $kind === null ? null : \SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind::from($kind), $kinds),
    );
    $keys = [];
    foreach ($value['keys'] as $key) {
        [$slot, $order] = $key;
        $keys[] = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexKey(
            $slot, \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexOrder::from($order),
            isset($key[2]) ? \SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation::from($key[2]) : null,
        );
    }
    $included = [];
    foreach ($value['included'] as $slot) {
        $included[] = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IncludedColumn($slot);
    }

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex(
        $owner, new $identifier($value['name']), $keys, $included,
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence::from($value['presence']),
        $value['ifNotExists'], $value['unique'], $value['nullsNotDistinct'], $value['concurrently'],
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
        constructPostgresIndexPredicate($value['predicate']),
    );
}

function observePostgresBtree(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex $index, bool $render): array
{
    $table = $index->table;
    $bindings = [];
    foreach ($index->bindings as $slot => $field) {
        $bindings[] = [$slot, $field->name->value, $field->kind->value];
    }
    return [
        'status' => 'constructed', 'profile' => $index->profileId(), 'sql' => $render ? $index->toString() : null,
        'createBtree' => [
            'table' => [$table->schema->value, $table->table->value, $table->only],
            'bindings' => $bindings,
            'name' => $index->name->value,
            'predicate' => observePostgresIndexPredicate($index->predicate),
            'keys' => array_map(static fn ($key) => [
                $key->slot, $key->order->value, ...($key->collation === null ? [] : [$key->collation->value]),
            ], $index->keys),
            'included' => array_map(static fn ($column) => $column->slot, $index->included),
            'presence' => $index->presence->value, 'ifNotExists' => $index->ifNotExists,
            'unique' => $index->unique, 'nullsNotDistinct' => $index->nullsNotDistinct, 'concurrently' => $index->concurrently,
        ],
    ];
}

function constructPostgresIndexTablespace(array $input): ?\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespace
{
    return isset($input['tablespace']) ? new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespace(
        new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($input['tablespace']),
    ) : null;
}

function observePostgresIndexTablespace(?\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespace $tablespace): array
{
    return $tablespace === null ? [] : ['tablespace' => $tablespace->name->value];
}

function constructPostgresReindex(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex
{
    $specification = $input['reindex'];
    [$schema, $index, $table] = array_map(
        static fn (string $name) => new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($name), $specification['target'],
    );
    $role = match (true) {
        ($specification['constraintOwned'] ?? false) => \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ConstraintIndexReference::class,
        ($specification['exclusionOwned'] ?? false) => \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ExclusionIndexReference::class,
        default => \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexReference::class,
    };
    $target = isset($specification['partitionKind'])
        ? new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionIndexReference(
            $schema, $index, $table,
            \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind::from($specification['partitionKind']),
        )
        : new $role($schema, $index, $table);

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex(
        $target,
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode::from($specification['mode']),
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
        $input['verbose'] ?? false,
        constructPostgresIndexTablespace($input),
    );
}

function observePostgresReindex(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex $value, bool $render): array
{
    $target = $value->target;
    return [
        'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $render ? $value->toString() : null, 'verbose' => $value->verbose,
        ...observePostgresIndexTablespace($value->tablespace),
        'reindex' => [
            'target' => [$target->schema->value, $target->index->value, $target->table->value],
            'mode' => $value->mode->value,
            ...($target instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ConstraintIndexReference ? ['constraintOwned' => true] : []),
            ...($target instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ExclusionIndexReference ? ['exclusionOwned' => true] : []),
            ...($target instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionIndexReference
                ? ['partitionKind' => $target->kind->value] : []),
        ],
    ];
}

function constructPostgresReindexTable(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTable
{
    $specification = $input['reindexTable'];
    [$schema, $owner, $kind] = $specification['target'];
    $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
    $target = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableTarget(
        new $name($schema), new $name($owner),
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableKind::from($kind),
    );

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTable(
        $target,
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode::from($specification['mode']),
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
        $input['verbose'] ?? false,
        constructPostgresIndexTablespace($input),
    );
}

function observePostgresReindexTable(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTable $value): array
{
    $target = $value->target;
    return [
        'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(), 'verbose' => $value->verbose,
        ...observePostgresIndexTablespace($value->tablespace),
        'reindexTable' => [
            'target' => [$target->schema->value, $target->name->value, $target->kind->value],
            'mode' => $value->mode->value,
        ],
    ];
}

function constructPostgresReindexPartitionTable(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\ReindexPartitionTable
{
    $value = $input['reindexPartitionTable'];
    [$schema, $owner] = $value['target'];
    $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\ReindexPartitionTable(
        new \SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName(new $name($schema), new $name($owner)),
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind::from($value['kind']),
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode::from($value['mode']),
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
        $input['verbose'] ?? false,
        constructPostgresIndexTablespace($input),
    );
}

function constructPostgresReindexSchema(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSchema
{
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSchema(
        new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($input['reindexSchema']['schema']),
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode::from($input['reindexSchema']['mode']),
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
        $input['verbose'] ?? false,
        constructPostgresIndexTablespace($input),
    );
}

function constructPostgresDatabaseRebuild(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabase|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSystemCatalogs
{
    $spec = $input['databaseRebuild'];
    $database = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($spec['database']);
    $release = \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']);
    return match ($spec['selector']) {
        'system' => new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSystemCatalogs($database, $release, $input['verbose'] ?? false, constructPostgresIndexTablespace($input)),
        'database' => new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabase(
            $database, \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode::from($spec['mode']), $release, $input['verbose'] ?? false, constructPostgresIndexTablespace($input),
        ),
    };
}

function constructPostgresOwnedColumn(?array $owner): ?\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner
{
    if ($owner === null) {
        return null;
    }
    [$namespace, $relation, $column, $kind] = $owner;

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner(
        new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($namespace),
        new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($relation),
        new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($column),
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwnerKind::from($kind),
    );
}

function constructPostgresSequence(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence
{
    $spec = $input['createSequence'];
    $number = static fn (?string $decimal): ?\SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral => $decimal === null
        ? null : new \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral($decimal);
    [$schema, $sequence] = $spec['target'];
    $name = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceName(
        new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($schema),
        new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($sequence),
    );
    $numbers = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceParameters(
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage::from($spec['storage']),
        new \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral($spec['increment']),
        $number($spec['minimum']), $number($spec['maximum']), $number($spec['start']), $number($spec['initial']),
        new \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral($spec['cache']),
        $spec['cycle'],
    );
    $owner = constructPostgresOwnedColumn($spec['owner'] ?? null);

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence(
        $name, $numbers, \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePersistence::from($spec['persistence']),
        $spec['ifNotExists'], \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']), $owner,
    );
}

function observePostgresSequence(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence $value): array
{
    $numbers = $value->parameters;
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'createSequence' => [
            'target' => [$value->name->schema->value, $value->name->name->value],
            'persistence' => $value->persistence->value, 'ifNotExists' => $value->ifNotExists,
            'owner' => $value->owner === null ? null
                : [$value->owner->schema->value, $value->owner->relation->value, $value->owner->column->value, $value->owner->kind->value],
            'storage' => $numbers->storage->value, 'increment' => $numbers->increment->decimal,
            'minimum' => $numbers->minimum->decimal, 'maximum' => $numbers->maximum->decimal,
            'start' => $numbers->start->decimal, 'initial' => $numbers->initial->decimal,
            'cache' => $numbers->cache->decimal, 'cycle' => $numbers->cycle,
        ]];
}

function constructPostgresSequenceSelector(array $value): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector
{
    [$scope, $sequence, $presence] = $value;

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector(
        new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($sequence),
        $scope === null ? null : new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($scope),
        \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence::from($presence),
    );
}

function observePostgresSequenceSelector(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector $target): array
{
    return [$target->schema?->value, $target->name->value, $target->presence->value];
}

function constructPostgresDropSequence(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequence
{
    $spec = $input['dropSequence'];
    $targets = array_map(constructPostgresSequenceSelector(...), $spec['targets']);

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequence(
        $targets, $spec['ifExists'], $spec['cascade'], \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
}

function observePostgresDropSequence(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequence $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'dropSequence' => [
            'targets' => array_map(observePostgresSequenceSelector(...), $value->targets),
            'ifExists' => $value->ifExists, 'cascade' => $value->cascade,
        ]];
}

function constructPostgresRenameSequence(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequence
{
    $spec = $input['renameSequence'];
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequence(
        constructPostgresSequenceSelector($spec['source']),
        new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($spec['destination']),
        $spec['ifExists'], \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
}

function observePostgresRenameSequence(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequence $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'renameSequence' => ['source' => observePostgresSequenceSelector($value->source),
            'destination' => $value->destination->value, 'ifExists' => $value->ifExists]];
}

function constructPostgresMoveSequence(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\MoveSequence
{
    $spec = $input['moveSequence'];
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\MoveSequence(
        constructPostgresSequenceSelector($spec['source']),
        $spec['destination'] === null ? null : new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($spec['destination']),
        $spec['ifExists'], \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
}

function observePostgresMoveSequence(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\MoveSequence $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'moveSequence' => ['source' => observePostgresSequenceSelector($value->source),
            'destination' => $value->destination?->value, 'ifExists' => $value->ifExists]];
}

function constructPostgresChangeBound(?string $operand): \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceResetOperand|null
{
    return match ($operand) {
        null => null,
        'native-default' => \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceResetOperand::Native,
        default => new \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral($operand),
    };
}

function constructPostgresSequenceChanges(array $body): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceChanges
{
    $number = static fn (string $key): ?\SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral => isset($body[$key])
        ? new \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral($body[$key]) : null;
    $owner = $body['owner'] ?? null;

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceChanges(
        isset($body['storage']) ? \SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage::from($body['storage']) : null,
        $number('increment'),
        constructPostgresChangeBound($body['minimum'] ?? null),
        constructPostgresChangeBound($body['maximum'] ?? null),
        $number('start'),
        constructPostgresChangeBound($body['restart'] ?? null),
        $number('cache'),
        $body['cycle'] ?? null,
        $owner === 'remove' ? \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ClearSequenceOwner::Remove : constructPostgresOwnedColumn($owner),
    );
}

function constructPostgresReconfigureSequence(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequence
{
    $spec = $input['reconfigureSequence'];

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequence(
        constructPostgresSequenceSelector($spec['source']),
        $spec['changes'] === null ? null : constructPostgresSequenceChanges($spec['changes']),
        $spec['ifExists'], \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
}

function observePostgresReconfigureSequence(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequence $value): array
{
    $changes = $value->changes;
    $bound = static fn ($operand): ?string => $operand instanceof \SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral
        ? $operand->decimal : $operand?->value;
    $owner = $changes?->owner;
    $encodedOwner = $owner instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner
        ? [$owner->schema->value, $owner->relation->value, $owner->column->value, $owner->kind->value] : $owner?->value;

    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'reconfigureSequence' => ['source' => observePostgresSequenceSelector($value->source), 'ifExists' => $value->ifExists,
            'changes' => $changes === null ? null : [
                'storage' => $changes->storage?->value, 'increment' => $changes->increment?->decimal,
                'minimum' => $bound($changes->minimum), 'maximum' => $bound($changes->maximum), 'start' => $changes->start?->decimal,
                'restart' => $bound($changes->restart), 'cache' => $changes->cache?->decimal, 'cycle' => $changes->cycle,
                'owner' => $encodedOwner,
            ]]];
}

function constructPostgresSequenceLogging(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLogging
{
    $spec = $input['sequenceLogging'];
    $modes = array_map(static fn (string $mode) => match ($mode) {
        'logged' => \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging::Logged,
        'unlogged' => \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging::Unlogged,
        default => $mode,
    }, $spec['modes']);

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLogging(
        constructPostgresSequenceSelector($spec['source']), $modes, $spec['ifExists'],
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
}

function observePostgresSequenceLogging(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLogging $value): array
{
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(), 'sequenceLogging' => [
        'source' => observePostgresSequenceSelector($value->source),
        'modes' => array_map(static fn ($mode): string => strtolower($mode->value), $value->modes), 'ifExists' => $value->ifExists,
    ]];
}

function constructPostgresSequenceAuthorization(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\AlterSequenceAuthorization
{
    $spec = $input['sequenceAuthorization'];
    $actions = [];
    foreach ($spec['actions'] as $action) {
        if (!is_array($action)) {
            $actions[] = $action;
            continue;
        }
        if (isset($action['logging'])) {
            $value = \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging::from(strtoupper($action['logging']));
        } else {
            $owner = $action['owner'];
            $principal = isset($owner['name'])
                ? new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($owner['name'])
                : \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole::from($owner['session']);
            $value = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal($principal);
        }
        $actions[] = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequenceAuthorizationAction($value);
    }

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\AlterSequenceAuthorization(
        constructPostgresSequenceSelector($spec['source']), $actions, $spec['ifExists'],
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
}

function observePostgresSequenceAuthorization(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\AlterSequenceAuthorization $value): array
{
    $encoded = [];
    foreach ($value->actions as $action) {
        $operation = $action->value;
        if ($operation instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging) {
            $encoded[] = ['logging' => strtolower($operation->value)];
        } else {
            $principal = $operation->owner;
            $encoded[] = ['owner' => [$principal instanceof \SqlSemantics\Platform\PostgreSql\Ir\Identifier ? 'name' : 'session' => $principal->value]];
        }
    }

    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(), 'sequenceAuthorization' => [
        'source' => observePostgresSequenceSelector($value->source), 'actions' => $encoded, 'ifExists' => $value->ifExists,
    ]];
}

function constructPostgresSequencePrincipal(array $role): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal
{
    $reference = isset($role['name'])
        ? new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($role['name'])
        : \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole::from($role['session']);
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal($reference);
}

function observePostgresSequencePrincipal(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal $role): array
{
    return [$role->owner instanceof \SqlSemantics\Platform\PostgreSql\Ir\Identifier ? 'name' : 'session' => $role->owner->value];
}

function constructPostgresSequenceGrantee(mixed $role): mixed
{
    if (!is_array($role)) {
        return $role;
    }
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee(
        isset($role['universal']) ? \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee::Everyone
            : constructPostgresSequencePrincipal($role),
    );
}

function observePostgresSequenceGrantee(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee $role): array
{
    return $role->role instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee
        ? ['universal' => true] : observePostgresSequencePrincipal($role->role);
}

function constructPostgresSequenceRight(string $right): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight|string
{
    return \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight::tryFrom($right) ?? $right;
}

function constructPostgresSequenceAccess(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\RevokeSequenceAccess
{
    $spec = $input['sequenceAccess'];
    $named = $spec['target']['named'] ?? null;
    $target = $named === null
        ? new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequencesInSchemas(array_map(
            static fn (string $name) => new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($name), $spec['target']['schemas'],
        ))
        : new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\NamedSequences(array_map(
            'constructPostgresSequenceSelector', $named,
        ));
    $rights = array_map('constructPostgresSequenceRight', $spec['rights']);
    $grantees = array_map('constructPostgresSequenceGrantee', $spec['grantees']);
    $access = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceAccess(
        $target, $rights, $grantees, $spec['grantor'] === null ? null : constructPostgresSequencePrincipal($spec['grantor']),
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
    if (isset($spec['grantOption'])) {
        return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess($access, $spec['grantOption']);
    }
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\RevokeSequenceAccess(
        $access, $spec['grantOptionOnly'], \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantDependency::from($spec['dependency']),
    );
}

function observePostgresSequenceAccess(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\RevokeSequenceAccess $value): array
{
    $access = $value->access;
    $target = $access->target instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\NamedSequences
        ? ['named' => array_map('observePostgresSequenceSelector', $access->target->sequences)]
        : ['schemas' => array_column($access->target->schemas, 'value')];
    $roles = array_map('observePostgresSequenceGrantee', $access->grantees);
    $operation = $value instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess
        ? ['grantOption' => $value->grantOption]
        : ['grantOptionOnly' => $value->grantOptionOnly, 'dependency' => $value->dependency->value];
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(), 'sequenceAccess' => [
        'target' => $target, 'rights' => array_column($access->rights, 'value'),
        'grantees' => $roles, 'grantor' => $access->grantor === null ? null : observePostgresSequencePrincipal($access->grantor), ...$operation,
    ]];
}

function constructPostgresSequenceDefaultAccess(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\GrantSequenceDefaults|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\RevokeSequenceDefaults
{
    $spec = $input['sequenceDefaultAccess'];
    $policy = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\SequenceDefaultPolicy(
        array_map(static fn (mixed $role) => is_array($role) ? constructPostgresSequencePrincipal($role) : $role, $spec['creators']),
        array_map(static fn (mixed $name) => is_string($name) ? new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($name) : $name, $spec['schemas']),
        array_map('constructPostgresSequenceRight', $spec['rights']),
        array_map('constructPostgresSequenceGrantee', $spec['grantees']),
        \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
    if (isset($spec['grantOption'])) {
        return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\GrantSequenceDefaults($policy, $spec['grantOption']);
    }
    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\RevokeSequenceDefaults(
        $policy, $spec['grantOptionOnly'], \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantDependency::from($spec['dependency']),
    );
}

function observePostgresSequenceDefaultAccess(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\GrantSequenceDefaults|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\RevokeSequenceDefaults $value): array
{
    $policy = $value->policy;
    $operation = $value instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\GrantSequenceDefaults
        ? ['grantOption' => $value->grantOption]
        : ['grantOptionOnly' => $value->grantOptionOnly, 'dependency' => $value->dependency->value];
    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(), 'sequenceDefaultAccess' => [
        'creators' => array_map('observePostgresSequencePrincipal', $policy->creators), 'schemas' => array_column($policy->schemas, 'value'),
        'rights' => array_column($policy->rights, 'value'), 'grantees' => array_map('observePostgresSequenceGrantee', $policy->grantees), ...$operation,
    ]];
}

function constructPostgresAlterSchema(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\ChangeSchemaOwner
{
    $spec = $input['alterSchema'];
    $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($spec['schema']);
    $release = \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']);
    if ($spec['destination'] !== null) {
        return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema(
            $name, new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($spec['destination']), $release,
        );
    }
    $owner = isset($spec['owner']['named'])
        ? new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($spec['owner']['named'])
        : \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole::from($spec['owner']['session']);

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\ChangeSchemaOwner($name, $owner, $release);
}

function observePostgresAlterSchema(
    \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema|\SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\ChangeSchemaOwner $value,
): array {
    if ($value instanceof \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema) {
        $destination = $value->destination->value;
        $owner = null;
    } else {
        $destination = null;
        $owner = $value->owner instanceof \SqlSemantics\Platform\PostgreSql\Ir\Identifier
            ? ['named' => $value->owner->value] : ['session' => $value->owner->value];
    }

    return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
        'alterSchema' => ['schema' => $value->schema->value, 'destination' => $destination, 'owner' => $owner]];
}

function constructPostgresCreateSchema(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema
{
    $spec = $input['createSchema'];
    $name = $spec['name'] === null ? null : new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($spec['name']);
    $owner = isset($spec['owner']['named'])
        ? new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($spec['owner']['named'])
        : \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole::from($spec['owner']['session']);
    $tables = [];
    foreach ($spec['tables'] ?? [] as $table) {
        $tables[] = constructCreateTable('SqlSemantics\\Platform\\PostgreSql\\', [
            'dialect' => 'postgres', 'release' => $table['release'], 'create' => $table,
        ]);
    }
    $sequences = [];
    foreach ($spec['sequences'] ?? [] as $sequence) {
        $sequences[] = constructPostgresSequence([
            'dialect' => 'postgres', 'release' => $sequence['release'], 'createSequence' => $sequence,
        ]);
    }
    $views = [];
    foreach ($spec['views'] ?? [] as $view) {
        $views[] = is_array($view) ? constructCreateView('SqlSemantics\\Platform\\PostgreSql\\', [
            'dialect' => 'postgres', 'release' => $view['release'], 'createView' => $view,
        ]) : $view;
    }

    $indexes = [];
    foreach ($spec['indexes'] ?? [] as $index) {
        $indexes[] = is_array($index) ? constructPostgresBtree([
            'dialect' => 'postgres', 'release' => $index['release'], 'createBtree' => $index,
        ]) : $index;
    }

    $grants = [];
    foreach ($spec['grants'] ?? [] as $grant) {
        $grants[] = is_array($grant) ? constructPostgresSequenceAccess([
            'dialect' => 'postgres', 'release' => $grant['release'], 'sequenceAccess' => $grant,
        ]) : $grant;
    }

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema(
        $name, $owner, $spec['ifNotExists'], \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
        $tables, $sequences, $views, $indexes, $grants,
    );
}

function observePostgresCreateSchema(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema $schema): array
{
    $owner = $schema->owner instanceof \SqlSemantics\Platform\PostgreSql\Ir\Identifier
        ? ['named' => $schema->owner->value] : ['session' => $schema->owner->value];

    return [
        'status' => 'constructed', 'profile' => $schema->profileId(), 'sql' => $schema->toString(),
        'createSchema' => ['name' => $schema->name?->value, 'owner' => $owner, 'ifNotExists' => $schema->ifNotExists],
        'schemaSequences' => array_map(
            static fn (\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence $sequence): array =>
                ['release' => $sequence->release->value] + observePostgresSequence($sequence)['createSequence'],
            $schema->sequences,
        ),
        'schemaTables' => array_map(static function (\SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable $table): array {
            $namespace = 'SqlSemantics\\Platform\\PostgreSql\\Ir\\';
            return [
                'release' => $table->release->value,
                'target' => [$table->target->schema->value, $table->target->table->value],
                'columns' => array_map(static fn ($column): array => [
                    $column->name->value, $column->storage->value, $column->nullable,
                    $column->default === null ? null : observeOperand($namespace, $column->default),
                    ...($column->collation === null ? [] : [$column->collation->value]),
                ], $table->columns),
                'ifNotExists' => $table->ifNotExists,
                ...($table->primaryKey === null ? [] : ['primaryKey' => observePostgresPrimaryKey($table->primaryKey)]),
                ...($table->uniqueKeys === [] ? [] : ['uniqueKeys' => array_map(observePostgresUniqueKey(...), $table->uniqueKeys)]),
            ];
        }, $schema->tables),
        'schemaViews' => array_map(
            static fn (\SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView $view): array =>
                ['release' => $view->query->release->value] + observePostgresCreateView($view),
            $schema->views,
        ),
        'schemaIndexes' => array_map(
            static fn (\SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex $index): array =>
                ['release' => $index->release->value] + observePostgresBtree($index, false)['createBtree'],
            $schema->indexes,
        ),
        'schemaGrants' => array_map(
            static fn (\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess $grant): array =>
                ['release' => $grant->access->release->value] + observePostgresSequenceAccess($grant)['sequenceAccess'],
            $schema->grants,
        ),
    ];
}

function constructPostgresDropSchema(array $input): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchema
{
    $drop = $input['dropSchema'];
    $targets = array_map(
        static fn (array $target): \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaDropTarget => new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaDropTarget(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier($target[0]), $target[1]),
        $drop['targets'],
    );

    return new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchema(
        $targets, $drop['ifExists'], $drop['cascade'], \SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::from($input['release']),
    );
}

function observePostgresDropSchema(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchema $drop): array
{
    return [
        'status' => 'constructed', 'profile' => $drop->profileId(), 'sql' => $drop->toString(),
        'dropSchema' => [
            'targets' => array_map(static fn (\SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaDropTarget $target): array => [$target->name->value, $target->present], $drop->targets),
            'ifExists' => $drop->ifExists,
            'cascade' => $drop->cascade,
        ],
    ];
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

function observeUpdate(string $namespace, object $value, string $dialect, bool $exactIntegral = false): array
{
    $sets = [];
    foreach ($value->assignments as $assignment) {
        $sets[] = [observeOperand($namespace, $assignment->target), observeOperand($namespace, $assignment->value)];
    }

    return [
        'status' => 'constructed', 'profile' => $value->profileId(), 'target' => observeSource($value->target, $dialect, $namespace, $exactIntegral),
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
        $namespace . 'BooleanLiteral', $namespace . 'NullLiteral', $namespace . 'ColumnReference', $namespace . 'From\\TextColumnReference', $namespace . 'From\\BooleanColumnReference', $namespace . 'From\\WideIntegerColumnReference', $namespace . 'From\\IntegralColumnReference', $namespace . 'IntegerLiteral', $namespace . 'Predicate\\RangeIntegerLiteral',
        $namespace . 'Predicate\\IntegerRange', $namespace . 'Predicate\\IntegerDistinctness', $namespace . 'Predicate\\IntegerMembership', $namespace . 'Predicate\\Text\\TextComparison', $namespace . 'Predicate\\TruthTest', $namespace . 'Predicate\\Truth\\TruthTest' => observeOperand($namespace, $condition),
    };
}

function observeDelete(string $namespace, object $value, string $dialect, bool $exactIntegral = false): array
{
    return [
        'status' => 'constructed', 'profile' => $value->profileId(),
        'target' => observeSource($value->target, $dialect, $namespace, $exactIntegral), 'where' => observePredicate($namespace, $value->where), 'sql' => $value->toString(),
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
            isset($input['mysqlCreateIndex']) => constructMysqlCreateIndex($platform, $input),
            isset($input['mysqlDropIndex']) => constructMysqlDropIndex($input),
            isset($input['mysqlDropIndexes']) => constructMysqlDropIndexes($input),
            isset($input['mysqlDropForeignKeys']) => constructMysqlDropForeignKeys($input),
            isset($input['mysqlAddForeignKeys']) => constructMysqlAddForeignKeys($input),
            isset($input['mysqlAlterForeignKeys']) => constructMysqlAlterForeignKeys($input),
            isset($input['mysqlIndexRenames']) => constructMysqlIndexRenames($input),
            isset($input['mysqlMixedIndexes']) => constructMysqlMixedIndexes($input),
            isset($input['mysqlIndexVisibility']) => constructMysqlIndexVisibility($input),
            isset($input['drop']) => constructDropTable($platform, $input),
            isset($input['postgresValues']) => constructPostgresValues($platform, $input),
            isset($input['createView']) => constructCreateView($platform, $input),
            isset($input['createBtree']) => constructPostgresBtree($input),
            isset($input['dropIndex']) => constructPostgresDropIndex($input),
            isset($input['createSchema']) => constructPostgresCreateSchema($input),
            isset($input['dropSchema']) => constructPostgresDropSchema($input),
            isset($input['alterSchema']) => constructPostgresAlterSchema($input),
            isset($input['createSequence']) => constructPostgresSequence($input),
            isset($input['dropSequence']) => constructPostgresDropSequence($input),
            isset($input['renameSequence']) => constructPostgresRenameSequence($input),
            isset($input['moveSequence']) => constructPostgresMoveSequence($input),
            isset($input['reconfigureSequence']) => constructPostgresReconfigureSequence($input),
            isset($input['sequenceLogging']) => constructPostgresSequenceLogging($input),
            isset($input['sequenceAuthorization']) => constructPostgresSequenceAuthorization($input),
            isset($input['sequenceAccess']) => constructPostgresSequenceAccess($input),
            isset($input['sequenceDefaultAccess']) => constructPostgresSequenceDefaultAccess($input),
            isset($input['reindex']) => constructPostgresReindex($input),
            isset($input['reindexTable']) => constructPostgresReindexTable($input),
            isset($input['reindexSchema']) => constructPostgresReindexSchema($input),
            isset($input['reindexPartitionTable']) => constructPostgresReindexPartitionTable($input),
            isset($input['databaseRebuild']) => constructPostgresDatabaseRebuild($input),
            isset($input['dropView']) => constructDropView($platform, $input),
            isset($input['materialization']) => constructPostgresMaterialization($platform, $input),
            isset($input['createTableAs']) => constructPostgresCreateAs($platform, $input),
            isset($input['rename']) => constructPostgresRename($platform, $input),
            isset($input['tableRename']) => constructMysqlRename($platform, $input),
            array_key_exists('mysqlTruncate', $input) => constructMysqlTruncate($platform, $input),
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
    } catch (ValueError $error) {
        $frame = $error->getTrace()[0] ?? [];
        if (($frame['class'] ?? null) === SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm::class
            && ($frame['function'] ?? null) === 'from') {
            return ['status' => 'rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
        }
        throw $error;
    } catch (TypeError $error) {
        $frame = $error->getTrace()[0] ?? [];
        $predicate = $input['createBtree']['predicate'] ?? null;
        if (is_array($predicate) && count($predicate) === 3 && $predicate[1] === null
            && ($frame['class'] ?? null) === SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanPredicate::class
            && ($frame['function'] ?? null) === '__construct'
            && str_contains($error->getMessage(), 'Argument #2 ($left) must be of type')) {
            return ['status' => 'rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
        }
        throw $error;
    }
    if ($value::class === $namespace . 'Schema\\Sequence\\CreateSequence') {
        return observePostgresSequence($value);
    }
    if ($value::class === $namespace . 'Schema\\Sequence\\DropSequence') {
        return observePostgresDropSequence($value);
    }
    if ($value::class === $namespace . 'Schema\\Sequence\\Alter\\RenameSequence') {
        return observePostgresRenameSequence($value);
    }
    if ($value::class === $namespace . 'Schema\\Sequence\\Alter\\MoveSequence') {
        return observePostgresMoveSequence($value);
    }
    if ($value::class === $namespace . 'Schema\\Sequence\\Alter\\ReconfigureSequence') {
        return observePostgresReconfigureSequence($value);
    }
    if ($value::class === $namespace . 'Schema\\Sequence\\Alter\\SetSequenceLogging') {
        return observePostgresSequenceLogging($value);
    }
    if ($value::class === $namespace . 'Schema\\Sequence\\Authorization\\AlterSequenceAuthorization') {
        return observePostgresSequenceAuthorization($value);
    }
    if (in_array($value::class, [$namespace . 'Schema\\Sequence\\Access\\GrantSequenceAccess', $namespace . 'Schema\\Sequence\\Access\\RevokeSequenceAccess'], true)) {
        return observePostgresSequenceAccess($value);
    }
    if (in_array($value::class, [$namespace . 'Schema\\Sequence\\Defaults\\GrantSequenceDefaults', $namespace . 'Schema\\Sequence\\Defaults\\RevokeSequenceDefaults'], true)) {
        return observePostgresSequenceDefaultAccess($value);
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
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex) {
        return observeMysqlDropIndex($value);
    }
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\AlterForeignKeys) {
        return observeMysqlAlterForeignKeys($value);
    }
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\AddForeignKeys) {
        return observeMysqlAddForeignKeys($value);
    }
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\DropForeignKeys) {
        return observeMysqlDropForeignKeys($value);
    }
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexes) {
        return observeMysqlDropIndexes($value);
    }
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes) {
        return observeMysqlMixedIndexes($value);
    }
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SetIndexVisibility) {
        return observeMysqlIndexVisibility($value);
    }
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\RenameIndexes) {
        return observeMysqlIndexRenames($value);
    }
    if ($value instanceof SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndex) {
        return observeMysqlCreateIndex($namespace, $value);
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
        if ($dialect === 'postgres' && $value->primaryKey !== null) {
            $observed['primaryKey'] = observePostgresPrimaryKey($value->primaryKey);
        }
        if ($dialect === 'postgres' && $value->uniqueKeys !== []) {
            $observed['uniqueKeys'] = array_map(observePostgresUniqueKey(...), $value->uniqueKeys);
        }
        foreach ($value->columns as $column) {
            $storage = $column->storage;
            $kind = $dialect === 'mysql' && $storage::class === $namespace . 'Schema\\CharacterStorage'
                ? ['varchar', $storage->length, $storage->collation->value] : $storage->value;
            $observed['columns'][] = [
                $column->name->value, $kind, $column->nullable,
                $column->default === null ? null : observeOperand($namespace, $column->default),
                ...($dialect === 'postgres' && $column->collation !== null ? [$column->collation->value] : []),
            ];
        }

        return ['status' => 'constructed', 'profile' => $value->profileId(), 'create' => $observed, 'sql' => $value->toString(),
            ...($dialect === 'mysql' ? ['createKind' => $value->target->kind->value] : [])];
    }
    if ($value::class === $namespace . 'Schema\\View\\CreateView') {
        if ($dialect === 'postgres') {
            return [
                'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
                'createView' => observePostgresCreateView($value),
            ];
        }
        return [
            'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(),
            'createView' => [
                'target' => [$value->name->schema->value, $value->name->view->value],
                'query' => observeSetQuery($namespace, $value->query, $input['dialect']),
                'columns' => array_map(static fn ($column): string|array =>
                    $column->name instanceof SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias
                        ? ['requested-result-alias', $column->name->requested, $column->name->value]
                        : $column->name->value, $value->columns),
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
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex) {
        return observePostgresBtree($value, !($input['constructionOnly'] ?? false));
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchema) {
        return observePostgresDropSchema($value);
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema) {
        return observePostgresCreateSchema($value);
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema
        || $value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\ChangeSchemaOwner) {
        return observePostgresAlterSchema($value);
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex) {
        return observePostgresDropIndex($value);
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex) {
        return observePostgresReindex($value, !($input['constructionOnly'] ?? false));
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTable) {
        return observePostgresReindexTable($value);
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSchema) {
        return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(), 'verbose' => $value->verbose,
            ...observePostgresIndexTablespace($value->tablespace),
            'reindexSchema' => ['schema' => $value->schema->value, 'mode' => $value->mode->value]];
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\ReindexPartitionTable) {
        return ['status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(), 'verbose' => $value->verbose,
            ...observePostgresIndexTablespace($value->tablespace),
            'reindexPartitionTable' => [
                'target' => [$value->target->schema->value, $value->target->table->value],
                'mode' => $value->mode->value, 'kind' => $value->kind->value,
            ]];
    }
    if ($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabase
        || $value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSystemCatalogs) {
        return [
            'status' => 'constructed', 'profile' => $value->profileId(), 'sql' => $value->toString(), 'verbose' => $value->verbose,
            ...observePostgresIndexTablespace($value->tablespace),
            'databaseRebuild' => [
                'database' => $value->database->value,
                ...($value instanceof SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabase
                    ? ['selector' => 'database', 'mode' => $value->mode->value] : ['selector' => 'system']),
            ],
        ];
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
        return observeUpdate($namespace, $value, $input['dialect'], ($input['observeExactIntegralSlots'] ?? false) === true);
    }
    if ($value::class === $namespace . 'Mutation\\InsertQuery') {
        return observeInsertQuery($namespace, $value, $input['dialect']);
    }
    if ($value::class === $namespace . 'Mutation\\Insert') {
        return observeInsert($namespace, $value, $input['dialect']);
    }
    if ($value::class === $namespace . 'Mutation\\Delete') {
        return observeDelete($namespace, $value, $input['dialect'], ($input['observeExactIntegralSlots'] ?? false) === true);
    }
    return [
        'status' => 'constructed',
        'profile' => $value->profileId(),
        ...observeSetQuery($namespace, $value, $input['dialect'], ($input['observeExactIntegralSlots'] ?? false) === true),
        ...(($input['observeSignedCaseLayouts'] ?? false) === true && $value instanceof SqlSemantics\Platform\MySql\Ir\Select
            ? ['signedCaseLayouts' => array_map($value->signedCaseLayout(...), array_keys($value->columns))] : []),
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
