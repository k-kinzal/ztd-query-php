<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

if ($argv[1] !== 'mysql') {
    throw new RuntimeException('The view definition observer requires MySQL.');
}
[$pdo, $environment] = connectFixture('mysql');
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The view definition observer requires the disposable ir_fixture database.');
}

function admitViewDefinition(string $sql): bool
{
    $word = '(?:[a-z_][a-z0-9_]*|`[a-z_][a-z0-9_ ]*`)';
    $table = '(?:ir_fixture|`ir_fixture`)\.';
    $target = $table . '(?:ir_cv_main|ir_cv_decoy|`ir_cv_main`|`ir_cv_decoy`)';
    $head = '(?: \(' . $word . '(?:, ?' . $word . '){0,4}\))?';
    $origin = $table . '(?:ir_cv_source|`ir_cv_source`)(?: AS ' . $word . ')?';
    $slot = '(?:' . $word . '\.)?(?:id|label|`id`|`label`)';
    $scalar = '(?:-?[0-9]{1,2}|NULL|' . $slot . ')';
    $input = $scalar . '(?: AS ' . $word . ')?';
    $filter = '(?: WHERE \(?(?:FALSE|TRUE|' . $slot . ' > [0-9])\)?)?';

    return preg_match('/\ACREATE(?: OR REPLACE)?(?: ALGORITHM=(?:UNDEFINED|MERGE|TEMPTABLE))?(?: SQL SECURITY (?:DEFINER|INVOKER))?'
        . ' VIEW (?:IF NOT EXISTS )?' . $target . $head . ' AS SELECT (?:DISTINCT )?' . $input . '(?:, ?' . $input . '){0,4}'
        . '(?: FROM (?:DUAL|' . $origin . '))?' . $filter . '(?: WITH (?:CASCADED |LOCAL )?CHECK OPTION)?\z/D', $sql) === 1;
}

function resetViewDefinition(PDO $pdo, bool $existing): void
{
    $pdo->exec('DROP VIEW IF EXISTS ir_fixture.ir_cv_main, ir_fixture.ir_cv_decoy');
    $pdo->exec('DROP TABLE IF EXISTS ir_fixture.ir_cv_source, ir_fixture.ir_cv_marker');
    $pdo->exec('CREATE TABLE ir_fixture.ir_cv_source (id INT, label VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO ir_fixture.ir_cv_source VALUES (1,\'alpha\'), (2,\'beta\'), (2,\'beta\'), (3,NULL), (NULL,\'orphan\')');
    $pdo->exec('CREATE TABLE ir_fixture.ir_cv_marker (value INT) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO ir_fixture.ir_cv_marker VALUES (0)');
    $pdo->exec('CREATE VIEW ir_fixture.ir_cv_decoy (decoy) AS SELECT 71');
    if ($existing) {
        $pdo->exec('CREATE ALGORITHM=TEMPTABLE SQL SECURITY INVOKER VIEW ir_fixture.ir_cv_main (original) AS SELECT id FROM ir_fixture.ir_cv_source WHERE id > 2');
    }
}

function viewDefinitionState(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT TABLE_NAME, IS_UPDATABLE, SECURITY_TYPE, CHECK_OPTION FROM information_schema.VIEWS"
        . " WHERE TABLE_SCHEMA = 'ir_fixture' AND TABLE_NAME IN ('ir_cv_main','ir_cv_decoy') ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_NUM);
    $views = [];
    foreach ($rows as [$name, $updatable, $security, $check]) {
        if ($name !== 'ir_cv_main' && $name !== 'ir_cv_decoy') {
            throw new RuntimeException('Unexpected view identity in the isolated definition fixture.');
        }
        $definition = $pdo->query('SHOW CREATE VIEW ir_fixture.' . $name)->fetch(PDO::FETCH_NUM);
        if (preg_match('/\ACREATE ALGORITHM=(UNDEFINED|MERGE|TEMPTABLE) /D', $definition[1], $matched) !== 1) {
            throw new RuntimeException('Expected a typed native view algorithm.');
        }
        $cursor = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME, IS_NULLABLE'
            . " FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'ir_fixture' AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
        $cursor->execute([$name]);
        $views[$name] = ['algorithm' => $matched[1], 'security' => $security, 'updatable' => $updatable,
            'check' => $check, 'columns' => $cursor->fetchAll(PDO::FETCH_NUM),
            'rows' => $pdo->query('SELECT * FROM ir_fixture.' . $name)->fetchAll(PDO::FETCH_NUM)];
    }

    return ['views' => $views, 'marker' => $pdo->query('SELECT value FROM ir_fixture.ir_cv_marker ORDER BY value')->fetchAll(PDO::FETCH_COLUMN)];
}

function viewDefinitionWriteEffects(PDO $pdo, array $names): array
{
    [$id, $label] = array_map(static fn (string $name): string => '`' . $name . '`', $names);
    $target = 'ir_fixture.ir_cv_main';
    $statements = [
        "INSERT INTO $target ($id,$label) VALUES (0,'low')",
        "INSERT INTO $target ($id,$label) VALUES (NULL,'unknown')",
        "INSERT INTO $target ($id,$label) VALUES (5,'high')",
        "UPDATE $target SET $id = 0 WHERE $id = 2",
    ];
    $pdo->beginTransaction();
    try {
        $effects = [];
        foreach ($statements as $statement) {
            try {
                $result = ['affected', $pdo->exec($statement)];
            } catch (PDOException $error) {
                $result = ['db-error', $error->errorInfo[0], $error->errorInfo[1]];
            }
            $base = $pdo->query('SELECT id, label FROM ir_fixture.ir_cv_source')->fetchAll(PDO::FETCH_NUM);
            $view = $pdo->query("SELECT * FROM $target")->fetchAll(PDO::FETCH_NUM);
            $effects[] = ['result' => $result, 'base' => $base, 'view' => $view];
        }

        return $effects;
    } finally {
        $pdo->rollBack();
    }
}

function viewDefinition(PDO $pdo, array $request): array
{
    $sql = $request['sql'];
    $writeNames = $request['writeNames'] ?? null;
    if (!admitViewDefinition($sql) || !is_bool($request['existing'] ?? null)
        || ($writeNames !== null && (!is_array($writeNames) || count($writeNames) !== 2
            || !array_is_list($writeNames) || array_filter($writeNames, static fn ($name): bool =>
                !is_string($name) || preg_match('/\A[a-z_][a-z0-9_]*\z/D', $name) !== 1) !== []))) {
        return ['status' => 'outside-observer-scope'];
    }
    resetViewDefinition($pdo, $request['existing']);
    $initial = viewDefinitionState($pdo);
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO ir_fixture.ir_cv_marker VALUES (7)');
    try {
        $pdo->exec($sql);
        $result = ['created'];
    } catch (PDOException $error) {
        $result = ['db-error', $error->errorInfo[0], $error->errorInfo[1]];
    }
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        $diagnostics = $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM);
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
    $pdo->query('SELECT 1')->fetchColumn();
    $active = $pdo->inTransaction();
    $after = viewDefinitionState($pdo);
    if ($active) {
        $pdo->rollBack();
    } else {
        $pdo->exec('ROLLBACK');
    }
    $rollback = viewDefinitionState($pdo);
    $writes = [];
    if ($writeNames !== null) {
        $installed = $result === ['created'] && !in_array(1050, array_column($diagnostics, 1), true);
        $writes = ['writeEffects' => $installed ? viewDefinitionWriteEffects($pdo, $writeNames) : 'definition-not-installed'];
    }
    $pdo->exec("UPDATE ir_fixture.ir_cv_source SET label = 'changed' WHERE id = 2");
    $pdo->exec("INSERT INTO ir_fixture.ir_cv_source VALUES (4,'new')");
    return ['status' => 'view-definition', 'result' => $result, 'diagnostics' => $diagnostics, 'active' => $active,
        'initial' => $initial, 'after' => $after, 'afterRollback' => $rollback, 'afterSourceChange' => viewDefinitionState($pdo), ...$writes];
}

resetViewDefinition($pdo, true);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => viewDefinitionState($pdo)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $responses = array_map(static fn (array $item): array => viewDefinition($pdo, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
