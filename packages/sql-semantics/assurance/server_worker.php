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

function constructSelect(string $platform, array $input): SemanticsIR
{
    $namespace = $platform . 'Ir\\';
    $identifier = $namespace . 'Identifier';
    $table = $namespace . 'TableSource';
    $column = $namespace . 'ResultColumn';
    $select = $namespace . 'Select';
    $release = $platform . 'Model\\Contract\\GrammarRelease';
    $source = $input['source'];
    $from = $source === null ? null : new $table(
        new $identifier($source['schema']),
        new $identifier($source['table']),
        array_map(static fn ($name) => new $identifier($name), $source['columns']),
        ...($input['dialect'] === 'postgres' ? [$source['only']] : []),
    );
    foreach ($input['columns'] as [$expr, $name]) {
        $columns[] = new $column(constructOperand($namespace, $expr), new $identifier($name));
    }

    return new $select($columns ?? [], $from, $release::from($input['release']));
}

function analyzeSelect(string $platform, array $input): SemanticsIR
{
    $class = $platform . 'Semantics';
    $semantics = new $class($input['release']);
    $declarations = [];
    foreach ($input['declarations'] as $ddl) {
        $declarations[] = $semantics->analyze($ddl, []);
    }

    return $semantics->ir()->analyze($input['sql'], $declarations === [] ? null : $declarations);
}

function observeSelect(array $input): array
{
    $platform = 'SqlSemantics\\Platform\\' . match ($input['dialect']) {
        'mysql' => 'MySql\\',
        'postgres' => 'PostgreSql\\',
    };
    try {
        $value = isset($input['sql']) ? analyzeSelect($platform, $input) : constructSelect($platform, $input);
    } catch (AnalysisException $error) {
        return ['status' => 'rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
    }
    $source = $value->source;

    return [
        'status' => 'constructed',
        'profile' => $value->profileId(),
        'columns' => array_map(static fn ($column): array => [observeOperand($platform . 'Ir\\', $column->expression), $column->name->value], $value->columns),
        'source' => $source === null ? null : [
            'schema' => $source->schema->value,
            'table' => $source->table->value,
            'columns' => array_map(static fn ($name): string => $name->value, $source->columns),
            ...($input['dialect'] === 'postgres' ? ['only' => $source->only] : []),
        ],
        'sql' => $value->toString(),
    ];
}

echo json_encode(['php' => PHP_VERSION, 'intSize' => PHP_INT_SIZE], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    try {
        echo json_encode(observeSelect(json_decode($line, true, 512, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        echo json_encode(['status' => 'error', 'exception' => $error::class, 'message' => $error->getMessage()], JSON_THROW_ON_ERROR) . "\n";
    }
}
