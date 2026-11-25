<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

[$pdo, $database] = connectFixture('mysql');
$principal = $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
echo json_encode(['database' => $database, 'php' => PHP_VERSION, 'principal' => $principal,
    'protocols' => ['native-prepared', 'text']], JSON_THROW_ON_ERROR) . "\n";

function viewAliasWarnings(PDO $pdo): array
{
    $emulated = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        return $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM);
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulated);
    }
}

function observeAliasRows(PDOStatement $data): array
{
    $fields = [];
    for ($index = 0; $index < $data->columnCount(); ++$index) {
        $metadata = $data->getColumnMeta($index);
        $fields[] = [bin2hex($metadata['name']), $metadata['native_type'], $metadata['len'], $metadata['flags']];
    }
    $rows = $data->fetchAll(PDO::FETCH_NUM);
    $data->closeCursor();

    return [$fields, $rows];
}

function observeStandaloneProjection(PDO $pdo, string $sql, bool $text): array
{
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->query('SELECT value FROM ir_fixture.sem_ir_view_alias_reset LIMIT 1')->closeCursor();
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $text);
    $statement = $pdo->prepare($sql);
    $prepare = viewAliasWarnings($pdo);
    $statement->execute();
    [$fields, $rows] = observeAliasRows($statement);
    $execute = viewAliasWarnings($pdo);

    return ['status' => 'rows', 'fields' => $fields, 'rows' => $rows,
        'prepareWarnings' => $prepare, 'executeWarnings' => $execute];
}

function readAliasView(PDO $pdo, bool $observeColumns): array
{
    $catalog = $pdo->query(
        "SELECT TABLE_SCHEMA, TABLE_NAME, CHECK_OPTION, IS_UPDATABLE, DEFINER, SECURITY_TYPE, CHARACTER_SET_CLIENT "
        . "FROM information_schema.VIEWS WHERE TABLE_SCHEMA = 'ir_fixture' AND TABLE_NAME = 'ir_cv_main'"
    )->fetchAll(PDO::FETCH_NUM);
    $definition = $pdo->query('SHOW CREATE VIEW ir_fixture.ir_cv_main')->fetch(PDO::FETCH_NUM);
    if (!preg_match('/\\ACREATE ALGORITHM=(UNDEFINED|MERGE|TEMPTABLE) /', $definition[1], $algorithm)) {
        throw new RuntimeException('The native view definition did not identify its algorithm.');
    }
    $data = $pdo->query('SELECT * FROM ir_fixture.ir_cv_main');
    [$fields, $rows] = observeAliasRows($data);

    $readWarnings = viewAliasWarnings($pdo);
    $result = ['catalog' => $catalog, 'algorithm' => $algorithm[1], 'definitionSql' => $definition[1],
        'fields' => $fields, 'rows' => $rows, 'readWarnings' => $readWarnings];
    if ($observeColumns) {
        $result['catalogColumns'] = $pdo->query(
            "SELECT HEX(COLUMN_NAME), ORDINAL_POSITION, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = 'ir_fixture' AND TABLE_NAME = 'ir_cv_main' ORDER BY ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_NUM);
    }

    return $result;
}

function observeViewAliasProtocol(PDO $pdo, string $sql, bool $text, bool $observeColumns): array
{
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->exec('DROP VIEW IF EXISTS ir_fixture.ir_cv_main');
    $pdo->query('SELECT value FROM ir_fixture.sem_ir_view_alias_reset LIMIT 1')->closeCursor();
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $text);
    $phase = 'prepare';
    try {
        $statement = $pdo->prepare($sql);
        $prepare = viewAliasWarnings($pdo);
        $phase = 'execute';
        $statement->execute();
        $execute = viewAliasWarnings($pdo);
        $phase = 'read';
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        return ['status' => 'created', 'prepareWarnings' => $prepare, 'executeWarnings' => $execute, ...readAliasView($pdo, $observeColumns)];
    } catch (PDOException $error) {
        $warnings = viewAliasWarnings($pdo);
        $exists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = 'ir_fixture' AND TABLE_NAME = 'ir_cv_main'"
        )->fetchColumn();
        return ['status' => 'native-refusal', 'phase' => $phase,
            'sqlState' => $error->errorInfo[0], 'code' => $error->errorInfo[1], 'message' => $error->errorInfo[2],
            'warnings' => $warnings, 'catalogExists' => (int) $exists !== 0];
    }
}

function observeViewAliases(PDO $pdo, array $input): array
{
    if (($input['fixture'] ?? false) === true) {
        $pdo->exec('CREATE TEMPORARY TABLE ir_fixture.sem_ir_view_alias_reset (value INT)');
        return ['status' => 'fixture'];
    }
    $sql = $input['sql'];
    if (($input['standaloneProjection'] ?? false) === true && str_starts_with($sql, 'SELECT ')) {
        return ['native-prepared' => observeStandaloneProjection($pdo, $sql, false),
            'text' => observeStandaloneProjection($pdo, $sql, true)];
    }
    if (!str_starts_with($sql, 'CREATE ') || !preg_match('/ VIEW (?:ir_fixture\.ir_cv_main|`ir_fixture`\.`ir_cv_main`) /', $sql)) {
        return ['status' => 'outside-view-alias-observer-scope'];
    }

    $observeColumns = ($input['catalogColumns'] ?? false) === true;

    return ['native-prepared' => observeViewAliasProtocol($pdo, $sql, false, $observeColumns),
        'text' => observeViewAliasProtocol($pdo, $sql, true, $observeColumns)];
}

while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $response = array_is_list($input)
        ? array_map(static fn (array $request): array => observeViewAliases($pdo, $request), $input)
        : observeViewAliases($pdo, $input);
    echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
}
