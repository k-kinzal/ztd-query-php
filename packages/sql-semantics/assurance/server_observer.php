<?php

declare(strict_types=1);

// This observer is started by the evidence process and never inside the candidate container.
$engine = $argv[1];
require __DIR__ . '/server_connection.php';
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
    $shadowRange = ($input['shadowInt4Range'] ?? false) === true;
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
        if ($warnings) {
            $result['warningCount'] = warningCount($pdo);
        }
    } catch (PDOException $error) {
        $result = ['status' => 'db-error', 'state' => $error->errorInfo[0]];
        $pdo->exec('ROLLBACK');
        $readOnly = false;
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
