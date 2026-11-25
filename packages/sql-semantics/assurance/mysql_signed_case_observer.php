<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

[$pdo, $environment] = connectFixture($argv[1]);
if ($argv[1] !== 'mysql' || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('Observe signed CASE only in the disposable MySQL ir_fixture.');
}

function observeCase(PDO $pdo, array $request): array
{
    if (isset($request['fixture'])) {
        foreach ($request['fixture'] as $sql) {
            $pdo->exec($sql);
        }
        return ['widths' => $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = 'ir_fixture' AND TABLE_NAME IN ('sem_ir_signed_membership', 'sem_ir_signed_membership_peer') "
            . 'ORDER BY TABLE_NAME, ORDINAL_POSITION')->fetchAll(PDO::FETCH_NUM)];
    }
    $sql = $request['sql'];
    if (!str_starts_with($sql, 'SELECT ') || str_contains($sql, ';') || str_contains($sql, '/*')
        || str_contains($sql, '--') || str_contains($sql, '#')) {
        return ['status' => 'outside-mysql-case-observer'];
    }
    $pdo->query('SELECT tiny_value FROM ir_fixture.sem_ir_signed_membership LIMIT 1')->closeCursor();
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $fields = [];
        for ($position = 0; $position < $statement->columnCount(); ++$position) {
            $meta = $statement->getColumnMeta($position);
            $fields[] = [$meta['name'], $meta['native_type'], !in_array('not_null', $meta['flags'], true),
                in_array('unsigned', $meta['flags'], true)];
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);
        $statement->closeCursor();
    } catch (PDOException $error) {
        return ['status' => 'mysql-case-native-error', 'sqlState' => $error->errorInfo[0], 'diagnostic' => $error->getMessage()];
    }
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        $warnings = (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    return ['status' => 'rows', 'fields' => $fields, 'rows' => $rows, 'warnings' => $warnings];
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $value = array_is_list($request)
        ? array_map(static fn (array $entry): array => observeCase($pdo, $entry), $request)
        : observeCase($pdo, $request);
    echo json_encode($value, JSON_THROW_ON_ERROR) . "\n";
}
