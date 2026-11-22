<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

$engine = $argv[1];
[$pdo, $environment] = connectFixture($engine);
$schema = $engine === 'mysql' ? 'ir_fixture' : 'public';
$database = $pdo->query($engine === 'mysql' ? 'SELECT DATABASE()' : 'SELECT current_database()')->fetchColumn();
if ($database !== 'ir_fixture') {
    throw new RuntimeException('The INSERT observer requires the disposable ir_fixture database.');
}

require __DIR__ . '/server_insert_support.php';

insertFixture($pdo, $engine, $schema);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => insertedRows($pdo, $schema)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($item) ? $item : [$item];
    $responses = [];
    foreach ($requests as $request) {
        $responses[] = insertEffect($pdo, $schema, $request['sql'], admitInsert(...), insertedRows(...));
    }
    echo json_encode(array_is_list($item) ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
