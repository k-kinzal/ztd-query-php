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
        'slot' => 'ColumnReference',
        'text-slot' => 'From\\TextColumnReference',
        'operation' => 'Arithmetic',
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
    $castTarget = $namespace . 'Expression\\CastDestination';
    $collation = $namespace . (str_contains($namespace, '\\MySql\\') ? 'From\\TextCollation' : 'Expression\\TextCollation');
    $textOperator = $namespace . 'Predicate\\Text\\TextOperator';
    $truth = $namespace . 'Predicate\\TruthOperator';

    return match ($data[0]) {
        'integer', 'range-integer', 'wide-integer' => new $type(...array_slice($data, 1)),
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
        'slot', 'text-slot' => new $type($data[1], $data[2] ?? 0),
        'null' => new $type(),
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
        $namespace . 'ColumnReference' => $value->occurrence === 0 ? ['slot', $value->slot] : ['slot', $value->slot, $value->occurrence],
        $namespace . 'From\\TextColumnReference' => ['text-slot', $value->slot, $value->occurrence],
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
    ] : [array_map(constructMySqlColumnType(...), $source['columnTypes'] ?? [])];

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

function constructSelect(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $identifier = $namespace . 'Identifier';
    $column = $namespace . 'ResultColumn';
    $select = $namespace . 'Select';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $order = $namespace . 'Ordering\\Order';
    $limit = $namespace . 'Ordering\\Limit';
    $keys = [];
    foreach ($input['orderBy'] ?? [] as $raw) {
        $keys[] = $raw === null ? null : new $order($raw[0], $raw[1], ...($input['dialect'] === 'postgres' ? [$raw[2]] : []));
    }
    $bound = $input['limit'] ?? null;
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
        $bound === null ? null : new $limit($bound[0], $bound[1]),
    );
}

function analyzeStatement(string $platform, array $input): SemanticsIR
{
    $class = $platform . 'Semantics';
    $semantics = new $class($input['release']);
    return $semantics->analyze($input['sql'], $semantics->context($input['declarations'] ?: null, $input['complete'] ?? true));
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
    $comparison = $namespace . 'Predicate\\IntegerComparison';
    $operator = $namespace . 'Predicate\\IntegerOperator';
    $logical = $namespace . 'Predicate\\LogicalPredicate';
    $logicalOperator = $namespace . 'Predicate\\LogicalOperator';
    $nullTest = $namespace . 'Predicate\\NullTest';
    return match ($test[0]) {
        'comparison' => new $comparison(
            $operator::from($test[1]),
            constructOperand($namespace, $test[2]),
            constructOperand($namespace, $test[3])
        ),
        'logic' => new $logical(
            $logicalOperator::from($test[1]),
            constructPredicate($namespace, $test[2]),
            constructPredicate($namespace, $test[3] ?? null),
        ),
        'null-test' => new $nullTest(constructPredicate($namespace, $test[2]), $test[1]),
        'boolean', 'null', 'slot', 'text-slot', 'integer', 'text-comparison', 'int4-range', 'signed-range', 'int4-distinctness', 'truth-test' => constructOperand($namespace, $test),
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
    $definition = $local['definition'];
    $source = isset($definition['local']) ? constructLocalProjection($platform, $definition) : constructSelect($platform, $definition);

    return new $statement($source, $columns);
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
    if (!isset($input['compound'])) {
        return constructSelect($platform, $input);
    }
    $namespace = $platform . 'Ir\\Rows\\';
    $type = $namespace . 'Compound';
    $operator = $namespace . 'CompoundOperator';
    $tree = $input['compound'];

    return new $type(
        constructSetQuery($platform, $tree['left']),
        $operator::from($tree['operator']),
        $tree['all'],
        constructSetQuery($platform, $tree['right']),
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
        'orderBy' => array_map(static fn ($key): array => [$key->position, $key->descending, $key->nullsFirst], $value->orderBy),
        'limit' => $value->limit === null ? null : [$value->limit->count, $value->limit->offset],
    ];
}

function constructGrouped(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $name = $namespace . 'Identifier';
    $output = $namespace . 'Aggregation\\GroupColumn';
    $aggregate = $namespace . 'Aggregation\\Aggregate';
    $operator = $namespace . 'Aggregation\\AggregateOperator';
    $select = $namespace . 'Aggregation\\GroupedSelect';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $grouped = $input['grouped'];
    $columns = [];
    foreach ($grouped['columns'] as [$expression, $label]) {
        $value = $expression[0] === 'aggregate'
            ? new $aggregate($operator::from($expression[1]), $expression[2] === null ? null : constructOperand($namespace, $expression[2]), $expression[3])
            : constructOperand($namespace, $expression);
        $columns[] = new $output($value, new $name($label));
    }
    return new $select(
        constructSource($platform, $grouped['source'], $input['dialect']),
        array_map(static fn (array $key): object => constructOperand($namespace, $key), $grouped['keys']),
        $columns,
        $release::from($input['release']),
        constructPredicate($namespace, $grouped['where']),
    );
}

function observeGrouped(string $namespace, object $query, string $dialect): array
{
    $columns = [];
    foreach ($query->columns as $field) {
        $value = $field->value;
        $expression = $value::class === $namespace . 'Aggregation\\Aggregate'
            ? ['aggregate', $value->operator->value, $value->argument === null ? null : observeOperand($namespace, $value->argument), $value->distinct]
            : observeOperand($namespace, $value);
        $columns[] = [$expression, $field->name->value];
    }

    return [
        'status' => 'constructed', 'profile' => $query->profileId(), 'sql' => $query->toString(),
        'grouped' => [
            'source' => observeSource($query->source, $dialect, $namespace), 'columns' => $columns,
            'keys' => array_map(static fn ($key): array => observeOperand($namespace, $key), $query->keys),
            'where' => observePredicate($namespace, $query->where),
        ],
    ];
}

function constructCreateTable(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $statement = $namespace . 'Schema\\CreateTable';
    $target = $namespace . 'Schema\\TableName';
    $name = $namespace . 'Identifier';
    $column = $namespace . 'Schema\\TableColumn';
    $storage = $namespace . 'Schema\\IntegerStorage';
    $characters = $namespace . 'Schema\\CharacterStorage';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $create = $input['create'];
    $columns = [];
    foreach ($create['columns'] as [$columnName, $kind, $nullable, $default]) {
        $type = $kind === 'pg_catalog.text' && $input['dialect'] === 'postgres' ? $characters::from($kind) : $storage::from($kind);
        $columns[] = new $column(new $name($columnName), $type, $nullable, $default === null ? null : constructOperand($namespace, $default));
    }

    return new $statement(
        new $target(new $name($create['target'][0]), new $name($create['target'][1])),
        $columns,
        $create['ifNotExists'],
        $release::from($input['release']),
    );
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
    foreach ($drop['targets'] as [$schema, $table]) {
        $targets[] = new $target(new $name($schema), new $name($table));
    }
    $version = $release::from($input['release']);

    return $input['dialect'] === 'postgres'
        ? new $statement($targets, $drop['ifExists'], $drop['cascade'], $version)
        : new $statement($targets, $drop['ifExists'], $version);
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
        static fn (array $slot): object => new $writeSlot($slot[1], $kind::from($slot[0] === 'text-slot' ? 'text' : 'int4'), $slot[2] ?? 0),
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
            static fn ($slot): array => $slot->kind->value === 'text' ? ['text-slot', $slot->slot, $slot->occurrence] : ['slot', $slot->slot],
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
        $namespace . 'BooleanLiteral', $namespace . 'NullLiteral', $namespace . 'ColumnReference', $namespace . 'From\\TextColumnReference', $namespace . 'IntegerLiteral',
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
    $platform = 'SqlSemantics\\Platform\\' . match ($input['dialect']) {
        'mysql' => 'MySql\\',
        'postgres' => 'PostgreSql\\',
    };
    $namespace = $platform . 'Ir\\';
    try {
        $value = match (true) {
            isset($input['sql']) => analyzeStatement($platform, $input),
            isset($input['grouped']) => constructGrouped($platform, $input),
            isset($input['local']) => constructLocalProjection($platform, $input),
            isset($input['series']) => constructRecursiveSeries($platform, $input),
            isset($input['compound']) => constructSetQuery($platform, $input),
            isset($input['create']) => constructCreateTable($platform, $input),
            isset($input['drop']) => constructDropTable($platform, $input),
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
    if ($value::class === $namespace . 'Schema\\CreateTable') {
        $observed = [
            'target' => [$value->target->schema->value, $value->target->table->value],
            'ifNotExists' => $value->ifNotExists,
            'columns' => [],
        ];
        foreach ($value->columns as $column) {
            $observed['columns'][] = [
                $column->name->value, $column->storage->value, $column->nullable,
                $column->default === null ? null : observeOperand($namespace, $column->default),
            ];
        }

        return ['status' => 'constructed', 'profile' => $value->profileId(), 'create' => $observed, 'sql' => $value->toString()];
    }
    if ($value::class === $namespace . 'Schema\\DropTable') {
        $observed = ['targets' => [], 'ifExists' => $value->ifExists];
        foreach ($value->targets as $target) {
            $observed['targets'][] = [$target->schema->value, $target->table->value];
        }
        if ($input['dialect'] === 'postgres') {
            $observed['cascade'] = $value->cascade;
        }
        return ['status' => 'constructed', 'profile' => $value->profileId(), 'drop' => $observed, 'sql' => $value->toString()];
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
