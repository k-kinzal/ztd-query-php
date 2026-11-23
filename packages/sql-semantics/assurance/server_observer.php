<?php

declare(strict_types=1);

// This observer is started by the evidence process and never inside the candidate container.
$engine = $argv[1];
require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_literal_observation.php';
[$pdo, $environment] = connectFixture($engine);

echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";

function measure(PDO $pdo, array $input, bool &$readOnly): array
{
    if (isset($input['fixture'])) {
        if ($readOnly) {
            $pdo->exec('ROLLBACK');
            $readOnly = false;
        }
        foreach ($input['fixture'] as $sql) {
            $pdo->exec($sql);
        }
        return ['status' => 'fixture'];
    }
    if (!$readOnly) {
        $pdo->exec('START TRANSACTION READ ONLY');
        $readOnly = true;
    }
    $warnings = ($input['observeWarnings'] ?? false) === true;
    if ($warnings) {
        // MySQL 5.x can retain earlier diagnostics when a constant statement succeeds.
        $pdo->query('SELECT 1 FROM ir_fixture.ir_evidence_accounts LIMIT 1')->closeCursor();
    }
    $shadowRange = ($input['shadowBuiltinOperators'] ?? false) === true;
    try {
        if ($shadowRange) {
            $pdo->exec('SET LOCAL search_path = public, pg_catalog');
        }
        $statement = $pdo->prepare($input['sql']);
        $statement->execute();
        $fields = [];
        for ($index = 0; $index < $statement->columnCount(); ++$index) {
            $meta = $statement->getColumnMeta($index);
            $fields[] = [$meta['name'], $meta['native_type']];
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);
        $statement->closeCursor();
        if ($shadowRange) {
            $pdo->exec('SET LOCAL search_path = pg_catalog, public');
        }
        $result = ['status' => 'rows', 'fields' => $fields, 'rows' => $rows];
        if (($input['observeMySqlLiterals'] ?? false) === true) {
            $result['literals'] = mysqlLiteralObservations($pdo, $input['sql']);
        }
        if (($input['observePostgresCollation'] ?? false) === true) {
            $result['collations'] = postgresResultCollations($pdo, $input['sql'], count($fields));
        }
        if ($warnings) {
            $result['warningCount'] = warningCount($pdo);
        }
    } catch (PDOException $error) {
        $result = ['status' => 'db-error', 'state' => $error->errorInfo[0]];
        if (($input['observeVendorCode'] ?? false) === true) {
            $result['vendorCode'] = (int) ($error->errorInfo[1] ?? 0);
        }
        $pdo->exec('ROLLBACK');
        $readOnly = false;
    }
    return $result;
}

function postgresResultCollations(PDO $pdo, string $sql, int $width): array
{
    $aliases = [];
    $observations = [];
    for ($index = 0; $index < $width; ++$index) {
        $alias = '"__ztd_column_' . $index . '"';
        $aliases[] = $alias;
        $observations[] = 'pg_catalog.pg_collation_for(' . $alias . ')';
    }
    // The generated aliases identify positions even with repeated labels. A null
    // extended row exposes the result's collation even when the query is empty.
    $statement = $pdo->query(
        'SELECT ' . implode(', ', $observations) . ' FROM (VALUES (1)) AS "__ztd_left" '
        . 'LEFT JOIN (' . $sql . ') AS "__ztd_target"(' . implode(', ', $aliases) . ') ON TRUE LIMIT 1'
    );
    $result = $statement->fetch(PDO::FETCH_NUM);
    $statement->closeCursor();
    if (!is_array($result)) {
        throw new RuntimeException('Expected a PostgreSQL collation observation for each result position.');
    }

    return $result;
}

function warningCount(PDO $pdo): int
{
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        $count = $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
        if (!is_numeric($count)) {
            throw new RuntimeException('Expected a server warning count.');
        }
        return (int) $count;
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
}

$readOnly = false;
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    if (array_is_list($input)) {
        $response = [];
        foreach ($input as $request) {
            $response[] = measure($pdo, $request, $readOnly);
        }
    } else {
        $response = measure($pdo, $input, $readOnly);
    }
    echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
}
