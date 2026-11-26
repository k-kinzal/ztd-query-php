<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
[$pdo, $environment] = connectFixture('mysql');
echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";

function projectionModeObservation(PDO $pdo, array $request): array
{
    if (isset($request['fixture'])) {
        foreach ($request['fixture'] as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec('START TRANSACTION READ ONLY');

        return ['status' => 'fixture'];
    }
    $active = match ($request['mode']) {
        'empty' => '',
        'ANSI_QUOTES' => 'ANSI_QUOTES',
    };
    $pdo->exec('SET SESSION sql_mode=' . $pdo->quote($active));
    $mode = $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    if ($mode !== $active) {
        throw new RuntimeException('The session did not select the requested lexical mode.');
    }
    $statement = $pdo->prepare($request['sql']);
    foreach ($request['bindings'] as $position => $binding) {
        $statement->bindValue($position + 1, $binding, $binding === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    }
    $statement->execute();
    $fields = [];
    for ($position = 0; $position < $statement->columnCount(); ++$position) {
        $meta = $statement->getColumnMeta($position);
        $fields[] = [$meta['name'], $meta['native_type']];
    }
    $rows = $statement->fetchAll(PDO::FETCH_NUM);
    $statement->closeCursor();

    return ['status' => 'rows', 'mode' => $request['mode'], 'bindings' => $request['bindings'], 'fields' => $fields, 'rows' => $rows];
}

while (($line = fgets(STDIN)) !== false) {
    try {
        $input = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        $observe = static fn (array $request): array => projectionModeObservation($pdo, $request);
        $response = array_is_list($input) ? array_map($observe, $input) : $observe($input);
    } catch (PDOException $error) {
        $response = ['status' => 'db-error', 'code' => $error->getCode(), 'nativeCode' => $error->errorInfo[1] ?? null];
    }
    echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
}
