<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

ini_set('mysqlnd.net_cmd_buffer_size', '65536');
if (ini_get('mysqlnd.net_cmd_buffer_size') !== '65536') {
    throw new RuntimeException('The signed projection observer requires its fixed metadata command buffer.');
}
[$pdo, $database] = connectFixture('mysql');
echo json_encode(['database' => $database, 'php' => PHP_VERSION, 'commandBuffer' => 65536], JSON_THROW_ON_ERROR) . "\n";

function observeSignedProjection(PDO $pdo, array $request): array
{
    if (($request['fixture'] ?? false) === true) {
        $pdo->exec('CREATE TEMPORARY TABLE ir_fixture.sem_ir_numeric_warning_reset (n INT)');
        return ['status' => 'fixture'];
    }
    $sql = $request['sql'];
    if (!str_starts_with($sql, 'SELECT ')) {
        return ['status' => 'outside-numeric-observer-scope'];
    }
    $pdo->query('SELECT n FROM ir_fixture.sem_ir_numeric_warning_reset LIMIT 1')->closeCursor();
    try {
        $query = $pdo->prepare($sql);
        $query->execute();
        $heading = [];
        for ($index = 0; $index < $query->columnCount(); ++$index) {
            $field = $query->getColumnMeta($index);
            $heading[] = [$field['name'], $field['native_type'], $field['flags'], $field['len']];
        }
        $values = $query->fetchAll(PDO::FETCH_NUM);
        $query->closeCursor();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        try {
            $warnings = $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM);
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        return ['status' => 'rows', 'fields' => $heading, 'rows' => $values, 'warnings' => $warnings];
    } catch (PDOException $error) {
        return ['status' => 'native-error', 'sqlState' => $error->errorInfo[0], 'code' => $error->errorInfo[1]];
    }
}

while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $response = array_is_list($input)
        ? array_map(static fn (array $request): array => observeSignedProjection($pdo, $request), $input)
        : observeSignedProjection($pdo, $input);
    echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
}
