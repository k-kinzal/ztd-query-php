<?php

declare(strict_types=1);

require '/stage/vendor/autoload.php';

foreach (['MySql' => 'app', 'PostgreSql' => 'public', 'Sqlite' => 'main'] as $platform => $schema) {
    $namespace = 'SqlSemantics\\Platform\\' . $platform . '\\';
    $analyzer = $namespace . 'Semantics';
    $semantics = new $analyzer();
    if (array_intersect(['inspect', 'diagnose', 'ir'], get_class_methods($semantics)) !== []
        || interface_exists('SqlSemantics\\Query') || class_exists($namespace . 'Model\\Statement\\Operation')
        || class_exists('SqlSemantics\\Testing\\' . $platform . '\\Inspection')) {
        throw new RuntimeException('The installed package exposes an obsolete publication path.');
    }

    $table = $schema . '.accounts';
    $context = $semantics->context(["CREATE TABLE $table (id INTEGER)"]);
    $reference = $semantics->analyze("SELECT id, accounts.id FROM $table", $context);
    if ($reference->columns[0]->expression !== $reference->columns[1]->expression) {
        throw new RuntimeException('The installed analyzer resolved different slots for equivalent references.');
    }
    $queries = [
        'Select' => 'SELECT 42 AS answer',
        'Rows\\Compound' => 'SELECT 1 AS a UNION ALL SELECT 2 AS b',
        'Mutation\\Delete' => "DELETE FROM $table WHERE id < 2",
        'Mutation\\Insert' => "INSERT INTO $table (id) VALUES (2)",
        'Mutation\\Update' => "UPDATE $table SET id = 2",
        'Schema\\CreateTable' => "CREATE TABLE $schema.other (id INTEGER)",
        'Schema\\DropTable' => "DROP TABLE IF EXISTS $table",
        'Transaction\\Transaction' => 'ROLLBACK TO SAVEPOINT bookmark',
    ];
    if ($platform === 'Sqlite') {
        $queries['Rows\\ValuesQuery'] = 'VALUES (1), (2)';
    }

    foreach ($queries as $role => $sql) {
        $result = $semantics->analyze($sql, $context);
        if (!$result instanceof SqlSemantics\SemanticsIR || $result::class !== $namespace . 'Ir\\' . $role) {
            throw new RuntimeException('The installed analyzer published an unexpected role.');
        }
        echo json_encode([
            'platform' => $platform,
            'role' => $role,
            'profile' => $result->profileId(),
            'path' => (new ReflectionClass($result))->getFileName(),
            'input' => $sql,
            'sql' => $result->toString(),
        ], JSON_THROW_ON_ERROR) . "\n";
    }
}
