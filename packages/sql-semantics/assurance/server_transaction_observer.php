<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

$engine = $argv[1];
[$writer, $environment] = connectFixture($engine);
[$reader, $readEnvironment] = connectFixture($engine);
$mysql = $engine === 'mysql';
$databaseQuery = $mysql ? 'SELECT DATABASE()' : 'SELECT current_database()';
foreach ([$writer, $reader] as $connection) {
    if ($connection->query($databaseQuery)->fetchColumn() !== 'ir_fixture') {
        throw new RuntimeException('The transaction observer requires the disposable ir_fixture database.');
    }
    if ($mysql) {
        $connection->exec('SET SESSION completion_type = 0');
    }
}
if ($environment !== $readEnvironment) {
    throw new RuntimeException('The observer connections require matching profiles.');
}
$table = ($mysql ? 'ir_fixture' : 'public') . '.ir_transaction_probe';
$writer->exec('CREATE TABLE ' . $table . ' (value INTEGER NOT NULL)' . ($mysql ? ' ENGINE=InnoDB' : ''));
$writer->exec('INSERT INTO ' . $table . ' VALUES (0)');
$delimiter = $mysql ? chr(96) : '"';

function transactionSyntax(bool $mysql, string $delimiter): string
{
    $bare = '[a-z_][a-z_0-9]{0,63}';
    $quoted = $delimiter . '(?:[^' . $delimiter . '\x00\r\n]|' . $delimiter . $delimiter . '){1,128}' . $delimiter;
    $label = '(?:' . $bare . '|' . $quoted . ')';
    $begin = 'BEGIN(?: WORK' . ($mysql ? '' : '| TRANSACTION') . ')?|START TRANSACTION';
    $options = '(?: WORK)?(?: AND NO CHAIN)?' . ($mysql ? '(?: NO RELEASE)?' : '');
    $end = '(?:COMMIT|ROLLBACK' . ($mysql ? '' : '|END|ABORT') . ')' . $options;

    return '/\A(?:' . $begin . '|' . $end . '|(?:SAVEPOINT|RELEASE SAVEPOINT|ROLLBACK TO(?: SAVEPOINT)?) ' . $label . ')\z/iuD';
}

function transactionStep(array $step, string $delimiter, string $table): string
{
    [$operation, $argument] = $step;
    if ($operation === 'write' && is_int($argument) && $argument >= -2147483648 && $argument <= 2147483647) {
        return 'INSERT INTO ' . $table . ' VALUES (' . $argument . ')';
    }
    if ($argument === null) {
        return match ($operation) {
            'begin' => 'START TRANSACTION',
            'commit' => 'COMMIT',
            'rollback' => 'ROLLBACK',
            default => throw new InvalidArgumentException('Unknown unnamed fixture command.'),
        };
    }
    if (!is_string($argument) || preg_match('/\A[^\x00\r\n]{1,64}\z/uD', $argument) !== 1) {
        throw new InvalidArgumentException('Invalid fixture checkpoint name.');
    }
    $verb = match ($operation) {
        'savepoint' => 'SAVEPOINT',
        'release' => 'RELEASE SAVEPOINT',
        'rollback-to' => 'ROLLBACK TO SAVEPOINT',
        default => throw new InvalidArgumentException('Unknown named fixture command.'),
    };

    return $verb . ' ' . $delimiter . str_replace($delimiter, $delimiter . $delimiter, $argument) . $delimiter;
}

function transactionState(PDO $writer, PDO $reader, string $table): array
{
    $sql = 'SELECT value FROM ' . $table . ' ORDER BY value';

    return [
        'active' => $writer->inTransaction(),
        'visible' => $writer->query($sql)->fetchAll(PDO::FETCH_COLUMN),
        'committed' => $reader->query($sql)->fetchAll(PDO::FETCH_COLUMN),
    ];
}

function transactionProgram(PDO $writer, PDO $reader, string $table, string $delimiter, string $syntax, array $request): array
{
    if (preg_match($syntax, $request['sql']) !== 1) {
        return ['status' => 'outside-observer-scope'];
    }
    $statements = null;
    $trace = [];
    try {
        $prefix = array_map(static fn (array $step): string => transactionStep($step, $delimiter, $table), $request['prefix']);
        $suffix = array_map(static fn (array $step): string => transactionStep($step, $delimiter, $table), $request['suffix']);
        $statements = [...$prefix, $request['sql'], ...$suffix];
    } catch (InvalidArgumentException $error) {
        return ['status' => 'outside-observer-scope'];
    }
    if (count($statements) > 24) {
        return ['status' => 'outside-observer-scope'];
    }
    $writer->exec('DELETE FROM ' . $table);
    $writer->exec('INSERT INTO ' . $table . ' VALUES (0)');
    try {
        foreach ($statements as $sql) {
            $writer->exec($sql);
            $trace[] = transactionState($writer, $reader, $table);
        }
    } catch (PDOException $error) {
        return ['status' => 'db-error', 'statement' => $sql, 'state' => $error->errorInfo[0], 'message' => $error->getMessage()];
    } finally {
        if ($writer->inTransaction()) {
            $writer->exec('ROLLBACK');
        }
    }

    return ['status' => 'observed', 'trace' => $trace];
}

$syntax = transactionSyntax($mysql, $delimiter);
echo json_encode([
    'database' => $environment, 'php' => PHP_VERSION, 'initial' => transactionState($writer, $reader, $table),
    'completionType' => $mysql ? $writer->query('SELECT @@session.completion_type')->fetchColumn() : null,
], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($item) ? $item : [$item];
    $responses = [];
    foreach ($requests as $request) {
        $responses[] = transactionProgram($writer, $reader, $table, $delimiter, $syntax, $request);
    }
    echo json_encode(array_is_list($item) ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
