<?php

declare(strict_types=1);

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\ResultColumn;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Semantics;

$root = $argv[1];
$overlay = $argv[2] ?? '';
spl_autoload_register(static function (string $class) use ($root, $overlay): void {
    foreach (['SqlSemantics\\Platform\\Sqlite\\' => 'sql-semantics-sqlite', 'SqlSemantics\\' => 'sql-semantics', 'SqlParser\\' => 'sql-parser'] as $prefix => $package) {
        if (str_starts_with($class, $prefix)) {
            $relative = $package . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            $patched = $overlay . '/' . $relative;
            require $overlay !== '' && is_file($patched) ? $patched : $root . '/packages/' . $relative;
            return;
        }
    }
});

function operand(array $input): Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter
{
    return match ($input[0]) {
        'integer' => new IntegerLiteral($input[1]),
        'null' => new NullLiteral(),
        'parameter' => new Parameter($input[1]),
        'column' => new ColumnReference($input[1]),
        'arithmetic' => new Arithmetic(ArithmeticOperator::from($input[1]), operand($input[2]), operand($input[3])),
    };
}

function observeOperand(Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter $value): array
{
    return match (true) {
        $value instanceof IntegerLiteral => ['integer', $value->decimal],
        $value instanceof NullLiteral => ['null'],
        $value instanceof Parameter => ['parameter', $value->label],
        $value instanceof ColumnReference => ['column', $value->slot],
        $value instanceof Arithmetic => ['arithmetic', $value->operator->value, observeOperand($value->left), observeOperand($value->right)],
    };
}

function direct(array $input): Select
{
    $source = $input['source'];
    $from = $source === null ? null : new TableSource(new Identifier($source[0]), new Identifier($source[1]), array_map(
        static fn (string $name): Identifier => new Identifier($name),
        $source[2],
    ));
    $columns = array_map(
        static fn (array $field): ResultColumn => new ResultColumn(operand($field[0]), new Identifier($field[1])),
        $input['columns'],
    );

    return new Select($columns, $from);
}

function analyzed(array $input): Select
{
    $semantics = new Semantics();
    $tables = [];
    foreach ($input['declarations'] as $ddl) {
        $tables[] = $semantics->analyze($ddl);
    }

    return $semantics->ir()->analyze($input['sql'], $tables === [] ? null : $tables);
}

function runCase(array $input): array
{
    try {
        $value = isset($input['sql']) ? analyzed($input) : direct($input);
    } catch (InvalidConstruction $error) {
        return ['status' => 'rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
    } catch (SqlSemantics\AnalysisException $error) {
        return ['status' => 'analysis-rejected', 'exception' => $error::class, 'message' => $error->getMessage()];
    }

    if (($input['probe'] ?? '') === 'snapshot') {
        $actions = [
            static fn () => clone $value,
            static fn () => serialize($value),
            static fn () => unserialize('O:' . strlen(Select::class) . ':"' . Select::class . '":0:{}'),
            static fn () => $value->extra = true,
        ];
        $blocked = [];
        foreach ($actions as $action) {
            try {
                $action();
                $blocked[] = false;
            } catch (InvalidConstruction) {
                $blocked[] = true;
            }
        }
        return ['status' => 'snapshot', 'blocked' => $blocked];
    }

    $source = $value->source;

    return [
        'status' => 'constructed',
        'profile' => $value->profileId(),
        'source' => $source === null ? null : [$source->schema->value, $source->table->value, array_map(
            static fn (Identifier $name): string => $name->value,
            $source->columns,
        )],
        'columns' => array_map(static fn (ResultColumn $field): array => [observeOperand($field->expression), $field->name->value], $value->columns),
        'sql' => $value->toString(),
    ];
}

echo json_encode(['php' => PHP_VERSION, 'intSize' => PHP_INT_SIZE], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    try {
        $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $observed = array_is_list($input) ? array_map(runCase(...), $input) : runCase($input);
        echo json_encode($observed, JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        echo json_encode(['status' => 'error', 'exception' => $error::class, 'message' => $error->getMessage()], JSON_THROW_ON_ERROR) . "\n";
    }
}
