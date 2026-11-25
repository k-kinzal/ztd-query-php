<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

ini_set('mysqlnd.net_cmd_buffer_size', '65536');
if (ini_get('mysqlnd.net_cmd_buffer_size') !== '65536') {
    throw new RuntimeException('The projection alias observer requires its fixed metadata command buffer.');
}
[$pdo, $database] = connectFixture('mysql');
echo json_encode(['database' => $database, 'php' => PHP_VERSION, 'protocols' => ['native-prepared', 'text']], JSON_THROW_ON_ERROR) . "\n";

function aliasWarnings(PDO $pdo): array
{
    $emulated = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        return $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM);
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulated);
    }
}

function observeAliasProtocol(PDO $pdo, string $sql, bool $text): array
{
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $text);
    $pdo->query('SELECT n FROM ir_fixture.sem_ir_alias_warning_reset LIMIT 1')->closeCursor();
    try {
        $statement = $pdo->prepare($sql);
        $prepareWarnings = aliasWarnings($pdo);
        $statement->execute();
        $fields = [];
        for ($index = 0; $index < $statement->columnCount(); ++$index) {
            $column = $statement->getColumnMeta($index);
            $fields[] = [bin2hex($column['name']), $column['native_type'], $column['flags'], $column['len']];
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);
        $statement->closeCursor();

        return ['status' => 'rows', 'fields' => $fields, 'rows' => $rows,
            'prepareWarnings' => $prepareWarnings, 'executeWarnings' => aliasWarnings($pdo)];
    } catch (PDOException $error) {
        return ['status' => 'native-error', 'sqlState' => $error->errorInfo[0], 'code' => $error->errorInfo[1]];
    }
}

function observeAliases(PDO $pdo, array $input): array
{
    if (($input['fixture'] ?? false) === true) {
        $pdo->exec('CREATE TEMPORARY TABLE ir_fixture.sem_ir_alias_warning_reset (n INT)');
        return ['status' => 'fixture'];
    }
    if (!str_starts_with($input['sql'], 'SELECT ')) {
        return ['status' => 'outside-alias-observer-scope'];
    }

    return ['native-prepared' => observeAliasProtocol($pdo, $input['sql'], false),
        'text' => observeAliasProtocol($pdo, $input['sql'], true)];
}

while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $response = array_is_list($input)
        ? array_map(static fn (array $request): array => observeAliases($pdo, $request), $input)
        : observeAliases($pdo, $input);
    echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
}
