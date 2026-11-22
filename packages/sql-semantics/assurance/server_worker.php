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
        'integer', 'boolean', 'slot' => new $type($data[1]),
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
        $namespace . 'ColumnReference' => ['slot', $value->slot],
        $namespace . 'Arithmetic' => ['operation', $value->operator->value,
            observeOperand($namespace, $value->left), observeOperand($namespace, $value->right)],
    };
}

function constructSource(string $namespace, array $source, string $dialect): object
{
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
    $from = $input['source'] === null ? null : constructSource($namespace, $input['source'], $input['dialect']);
    foreach ($input['columns'] as [$expr, $name]) {
        $columns[] = new $column(constructOperand($namespace, $expr), new $identifier($name));
    }

    return new $select($columns ?? [], $from, $release::from($input['release']));
}

function analyzeStatement(string $platform, array $input): SemanticsIR
{
    $class = $platform . 'Semantics';
    $semantics = new $class($input['release']);
    return $semantics->analyze($input['sql'], $semantics->context($input['declarations'] ?: null));
}

function observeSource(object $source, string $dialect): array
{
    return [
        'schema' => $source->schema->value,
        'table' => $source->table->value,
        'columns' => array_map(static fn ($name): string => $name->value, $source->columns),
        ...($dialect === 'postgres' ? ['only' => $source->only] : []),
    ];
}

function constructDelete(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $delete = $namespace . 'Mutation\\Delete';
    $comparison = $namespace . 'Predicate\\IntegerComparison';
    $operator = $namespace . 'Predicate\\IntegerOperator';
    $test = $input['where'];
    $where = match (true) {
        $test === null => null,
        $test[0] === 'comparison' => new $comparison(
            $operator::from($test[1]),
            constructOperand($namespace, $test[2]),
            constructOperand($namespace, $test[3])
        ),
        default => constructOperand($namespace, $test),
    };

    return new $delete(constructSource($namespace, $input['target'], $input['dialect']), $where, $release::from($input['release']));
}

function observeDelete(string $namespace, object $value, string $dialect): array
{
    $where = $value->where;
    if ($where === null) {
        $test = null;
    } elseif ($where::class === $namespace . 'Predicate\\IntegerComparison') {
        $test = ['comparison', $where->operator->value, observeOperand($namespace, $where->left), observeOperand($namespace, $where->right)];
    } else {
        $test = observeOperand($namespace, $where);
    }

    return [
        'status' => 'constructed', 'profile' => $value->profileId(),
        'target' => observeSource($value->target, $dialect), 'where' => $test, 'sql' => $value->toString(),
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
            isset($input['target']) => constructDelete($platform, $input),
            default => constructSelect($platform, $input),
        };
    } catch (AnalysisException $error) {
        return ['status' => 'rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
    }
    if ($value::class === $namespace . 'Mutation\\Delete') {
        return observeDelete($namespace, $value, $input['dialect']);
    }
    $source = $value->source;

    return [
        'status' => 'constructed',
        'profile' => $value->profileId(),
        'columns' => array_map(static fn ($column): array => [observeOperand($namespace, $column->expression), $column->name->value], $value->columns),
        'source' => $source === null ? null : observeSource($source, $input['dialect']),
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
