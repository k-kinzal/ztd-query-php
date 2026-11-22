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
        foreach ($input['fixture'] as $sql) {
            $pdo->exec($sql);
        }
        return ['status' => 'fixture'];
    }
    if (!$readOnly) {
        $pdo->exec('START TRANSACTION READ ONLY');
        $readOnly = true;
    }
    try {
        $statement = $pdo->prepare($input['sql']);
        $statement->execute();
        $fields = [];
        for ($index = 0; $index < $statement->columnCount(); ++$index) {
            $meta = $statement->getColumnMeta($index);
            $fields[] = [$meta['name'], $meta['native_type']];
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);
        $statement->closeCursor();
        $result = ['status' => 'rows', 'fields' => $fields, 'rows' => $rows];
    } catch (PDOException $error) {
        $result = ['status' => 'db-error', 'state' => $error->errorInfo[0]];
        $pdo->exec('ROLLBACK');
        $readOnly = false;
    }
    return $result;
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
