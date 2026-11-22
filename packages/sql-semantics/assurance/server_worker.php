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
        'boolean' => 'BooleanLiteral',
        'null' => 'NullLiteral',
        'slot' => 'ColumnReference',
        'operation' => 'Arithmetic',
    };
    $operator = $namespace . 'ArithmeticOperator';

    return match ($data[0]) {
        'integer', 'boolean' => new $type($data[1]),
        'slot' => new $type($data[1], $data[2] ?? 0),
        'null' => new $type(),
        'operation' => new $type(
            $operator::from($data[1]),
            constructOperand($namespace, $data[2]),
            constructOperand($namespace, $data[3]),
        ),
    };
}

function observeOperand(string $namespace, object $value): array
{
    return match ($value::class) {
        $namespace . 'IntegerLiteral' => ['integer', $value->decimal],
        $namespace . 'BooleanLiteral' => ['boolean', $value->value],
        $namespace . 'NullLiteral' => ['null'],
        $namespace . 'ColumnReference' => $value->occurrence === 0 ? ['slot', $value->slot] : ['slot', $value->slot, $value->occurrence],
        $namespace . 'Arithmetic' => ['operation', $value->operator->value,
            observeOperand($namespace, $value->left), observeOperand($namespace, $value->right)],
    };
}

function constructSource(string $namespace, array $source, string $dialect): object
{
    if (isset($source['kind'])) {
        $join = $namespace . 'From\\Join';
        $kind = $namespace . 'From\\JoinKind';

        return new $join(
            $kind::from($source['kind']),
            constructSource($namespace, $source['left'], $dialect),
            constructSource($namespace, $source['right'], $dialect),
            constructPredicate($namespace, $source['on']),
        );
    }
    $identifier = $namespace . 'Identifier';
    $table = $namespace . 'TableSource';

    return new $table(
        new $identifier($source['schema']),
        new $identifier($source['table']),
        array_map(static fn ($name) => new $identifier($name), $source['columns']),
        ...($dialect === 'postgres' ? [$source['only']] : []),
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
    $from = $input['source'] === null ? null : constructSource($namespace, $input['source'], $input['dialect']);
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
    return $semantics->analyze($input['sql'], $semantics->context($input['declarations'] ?: null));
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

    return [
        'schema' => $source->schema->value,
        'table' => $source->table->value,
        'columns' => array_map(static fn ($name): string => $name->value, $source->columns),
        ...($dialect === 'postgres' ? ['only' => $source->only] : []),
    ];
}

function constructPredicate(string $namespace, ?array $test): ?object
{
    $comparison = $namespace . 'Predicate\\IntegerComparison';
    $operator = $namespace . 'Predicate\\IntegerOperator';
    return match (true) {
        $test === null => null,
        $test[0] === 'comparison' => new $comparison(
            $operator::from($test[1]),
            constructOperand($namespace, $test[2]),
            constructOperand($namespace, $test[3])
        ),
        default => constructOperand($namespace, $test),
    };
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
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $target = constructSource($namespace, $input['target'], $input['dialect']);
    $sets = [];
    foreach ($input['assignments'] as [$slot, $value]) {
        $sets[] = new $assignment(constructOperand($namespace, $slot), constructOperand($namespace, $value));
    }

    return new $statement($target, $sets, constructPredicate($namespace, $input['where']), $release::from($input['release']));
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
    $target = constructSource($namespace, $input['target'], $input['dialect']);
    $rows = [];
    foreach ($input['rows'] as $entries) {
        $values = [];
        foreach ($entries as $cell) {
            $values[] = new $cellClass($cell === null ? null : new $integer($cell));
        }
        $rows[] = new $rowClass($values);
    }

    return new $statement($target, $rows, $release::from($input['release']));
}

function observeInsert(string $namespace, object $value, string $dialect): array
{
    $rows = [];
    foreach ($value->rows as $row) {
        $rows[] = array_map(static fn ($cell): ?string => $cell->value?->decimal, $row->values);
    }

    return [
        'status' => 'constructed', 'profile' => $value->profileId(), 'target' => observeSource($value->target, $dialect, $namespace),
        'rows' => $rows, 'sql' => $value->toString(),
    ];
}

function constructDelete(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $delete = $namespace . 'Mutation\\Delete';
    $where = constructPredicate($namespace, $input['where']);

    return new $delete(constructSource($namespace, $input['target'], $input['dialect']), $where, $release::from($input['release']));
}

function observePredicate(string $namespace, ?object $condition): ?array
{
    if ($condition === null) {
        return null;
    }
    if ($condition::class === $namespace . 'Predicate\\IntegerComparison') {
        return ['comparison', $condition->operator->value, observeOperand($namespace, $condition->left), observeOperand($namespace, $condition->right)];
    }

    return observeOperand($namespace, $condition);
}

function observeDelete(string $namespace, object $value, string $dialect): array
{
    return [
        'status' => 'constructed', 'profile' => $value->profileId(),
        'target' => observeSource($value->target, $dialect, $namespace), 'where' => observePredicate($namespace, $value->where), 'sql' => $value->toString(),
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
            isset($input['drop']) => constructDropTable($platform, $input),
            isset($input['command']) => constructTransaction($platform, $input),
            isset($input['target'], $input['assignments']) => constructUpdate($platform, $input),
            isset($input['target'], $input['rows']) => constructInsert($platform, $input),
            isset($input['target']) => constructDelete($platform, $input),
            default => constructSelect($platform, $input),
        };
    } catch (AnalysisException $error) {
        return ['status' => 'rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
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
    if ($value::class === $namespace . 'Mutation\\Insert') {
        return observeInsert($namespace, $value, $input['dialect']);
    }
    if ($value::class === $namespace . 'Mutation\\Delete') {
        return observeDelete($namespace, $value, $input['dialect']);
    }
    $source = $value->source;

    return [
        'status' => 'constructed',
        'profile' => $value->profileId(),
        'columns' => array_map(static fn ($column): array => [observeOperand($namespace, $column->expression), $column->name->value], $value->columns),
        'source' => $source === null ? null : observeSource($source, $input['dialect'], $namespace),
        'where' => observePredicate($namespace, $value->where),
        'distinct' => $value->distinct,
        'orderBy' => array_map(static fn ($key): array => [$key->position, $key->descending, $key->nullsFirst], $value->orderBy),
        'limit' => $value->limit === null ? null : [$value->limit->count, $value->limit->offset],
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
