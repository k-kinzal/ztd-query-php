<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

$engine = $argv[1];
[$pdo, $environment] = connectFixture($engine);
if ($engine !== 'mysql' || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The signed membership observer requires the disposable MySQL ir_fixture.');
}

const MEMBERSHIP_TABLE = '`ir_fixture`.`sem_ir_signed_membership`';
const MEMBERSHIP_PEER = '`ir_fixture`.`sem_ir_signed_membership_peer`';
const MEMBERSHIP_FIELDS = '`tiny_value`, `small_value`, `medium_value`, `int_value`, `wide_value`';

function membershipWarningCount(PDO $pdo): int
{
    // Some MySQL releases reject SHOW WARNINGS in the prepared-statement protocol.
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        return (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
}

function membershipRows(PDO $pdo, string $sql): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute();
    $fields = [];
    for ($position = 0; $position < $statement->columnCount(); ++$position) {
        $native = $statement->getColumnMeta($position);
        $fields[] = [$native['name'], $native['native_type']];
    }
    $rows = $statement->fetchAll(PDO::FETCH_NUM);
    $statement->closeCursor();
    $warnings = membershipWarningCount($pdo);

    return ['status' => 'rows', 'fields' => $fields, 'rows' => $rows, 'warnings' => $warnings];
}

function membershipFixture(PDO $pdo, array $fixture): array
{
    foreach ($fixture as $sql) {
        $pdo->exec($sql);
    }

    return [
        'table' => membershipRows($pdo, 'SELECT ' . MEMBERSHIP_FIELDS . ' FROM ' . MEMBERSHIP_TABLE),
        'peer' => membershipRows($pdo, 'SELECT ' . MEMBERSHIP_FIELDS . ' FROM ' . MEMBERSHIP_PEER),
        'truth' => membershipRows($pdo, 'SELECT (2 IN (2)) AS yes, (2 NOT IN (2)) AS no, (2 IN (1, NULL)) AS unknown_value'),
        'widths' => $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = 'ir_fixture' AND TABLE_NAME IN ('sem_ir_signed_membership', 'sem_ir_signed_membership_peer') "
            . 'ORDER BY TABLE_NAME, ORDINAL_POSITION')->fetchAll(PDO::FETCH_NUM),
    ];
}

function membershipEffect(PDO $pdo, string $command, string $role): array
{
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare($command);
        $statement->execute();
        $count = $statement->rowCount();
        $statement->closeCursor();
        $warnings = membershipWarningCount($pdo);
        $effect = ['status' => $role, 'count' => $count, 'warnings' => $warnings,
            'after' => membershipRows($pdo, 'SELECT ' . MEMBERSHIP_FIELDS . ' FROM ' . MEMBERSHIP_TABLE)];
    } finally {
        $pdo->rollBack();
    }
    $effect['restored'] = membershipRows($pdo, 'SELECT ' . MEMBERSHIP_FIELDS . ' FROM ' . MEMBERSHIP_TABLE);
    return $effect;
}

function observeMembership(PDO $pdo, array $request): array
{
    if (isset($request['fixture'])) {
        return membershipFixture($pdo, $request['fixture']);
    }
    $sql = $request['sql'];
    $role = $request['placement'];
    $prefixes = match ($role) {
        'projection', 'filter', 'join' => ['SELECT '],
        'delete' => ['DELETE FROM ' . MEMBERSHIP_TABLE . ' WHERE '],
        'update' => ['UPDATE ' . MEMBERSHIP_TABLE . ' SET ', 'UPDATE ' . MEMBERSHIP_TABLE . ' AS `__ztd_source` SET '],
    };
    $targetMatches = false;
    foreach ($prefixes as $prefix) {
        $targetMatches = $targetMatches || str_starts_with($sql, $prefix);
    }
    if (!$targetMatches || str_contains($sql, ';') || str_contains($sql, '/*')
        || str_contains($sql, '--') || str_contains($sql, '#')) {
        return ['status' => 'outside-mysql-membership-observer'];
    }
    // A table read clears warning state retained by earlier constant statements.
    $pdo->query('SELECT 1 FROM ' . MEMBERSHIP_TABLE . ' LIMIT 1')->closeCursor();
    try {
        return $role === 'update' || $role === 'delete' ? membershipEffect($pdo, $sql, $role) : membershipRows($pdo, $sql);
    } catch (PDOException $error) {
        return ['status' => 'mysql-membership-native-error', 'sqlState' => $error->errorInfo[0], 'diagnostic' => $error->getMessage()];
    }
}

echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $value = array_is_list($request)
        ? array_map(static fn (array $entry): array => observeMembership($pdo, $entry), $request)
        : observeMembership($pdo, $request);
    echo json_encode($value, JSON_THROW_ON_ERROR) . "\n";
}
