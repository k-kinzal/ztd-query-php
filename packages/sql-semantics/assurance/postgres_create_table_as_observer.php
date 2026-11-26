<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

[$pdo, $environment] = connectFixture('postgres');
if ($pdo->query('SELECT current_database()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The create-table-as observer requires the disposable ir_fixture database.');
}

function resetCreateAsFixture(PDO $pdo, string $fixture): void
{
    $kind = $pdo->query("SELECT relkind FROM pg_catalog.pg_class WHERE oid = to_regclass('public.ir_ct_target')")->fetchColumn();
    if (is_string($kind)) {
        if (!in_array($kind, ['r', 'v'], true)) {
            throw new RuntimeException('The create-table-as fixture found an unexpected catalog object.');
        }
        $pdo->exec('DROP ' . ($kind === 'r' ? 'TABLE' : 'VIEW') . ' public.ir_ct_target');
    }
    $pdo->exec('DROP SCHEMA IF EXISTS ir_ct_decoy CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS public.ir_ct_source, public.ir_ct_marker');
    $pdo->exec('CREATE SCHEMA ir_ct_decoy');
    $pdo->exec('CREATE TABLE ir_ct_decoy.ir_ct_target (id integer, label text)');
    $pdo->exec("INSERT INTO ir_ct_decoy.ir_ct_target VALUES (13,'decoy')");
    $pdo->exec('CREATE TABLE public.ir_ct_source (id integer, label text)');
    $pdo->exec("INSERT INTO public.ir_ct_source VALUES (NULL,'null'), (3,'same'), (3,'same'), (8,'changed')");
    $pdo->exec('CREATE TABLE public.ir_ct_marker (value integer)');
    $pdo->exec('INSERT INTO public.ir_ct_marker VALUES (0)');
    if ($fixture === 'table') {
        $pdo->exec('CREATE TABLE public.ir_ct_target (id integer, label text)');
        $pdo->exec("INSERT INTO public.ir_ct_target VALUES (17,'retained')");
    } elseif ($fixture === 'view') {
        $pdo->exec("CREATE VIEW public.ir_ct_target (id, label) AS VALUES (17, 'retained')");
    }
}

function observeCreateAsStep(PDO $pdo, string $sql): array
{
    $pdo->exec('SAVEPOINT ct_observe');
    try {
        $pdo->exec($sql);
        $result = ['executed'];
    } catch (PDOException $error) {
        $result = ['db-error', $error->errorInfo[0]];
        $pdo->exec('ROLLBACK TO SAVEPOINT ct_observe');
    }
    $pdo->exec('RELEASE SAVEPOINT ct_observe');

    return $result;
}

function createAsCatalog(PDO $pdo): array
{
    $relations = $pdo->query(<<<'SQL'
        SELECT n.nspname, c.oid, c.relkind, c.relpersistence, a.amname
        FROM pg_catalog.pg_class AS c
        JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
        LEFT JOIN pg_catalog.pg_am AS a ON a.oid = c.relam
        WHERE n.nspname IN ('public', 'ir_ct_decoy') AND c.relname = 'ir_ct_target'
        ORDER BY n.nspname
        SQL)->fetchAll(PDO::FETCH_NUM);
    $tables = [];
    foreach ($relations as [$schema, $oid, $kind, $persistence, $access]) {
        $fields = $pdo->query('SELECT a.attname,t.typname,CAST(t.oid AS integer),a.attnotnull'
            . ' FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_type t ON a.atttypid=t.oid'
            . ' WHERE a.attrelid=' . (int) $oid . ' AND a.attnum > 0 AND NOT a.attisdropped ORDER BY a.attnum')->fetchAll(PDO::FETCH_NUM);
        $rows = $pdo->query('SELECT * FROM ' . $schema . '.ir_ct_target')->fetchAll(PDO::FETCH_NUM);
        $tables[$schema] = ['kind' => $kind, 'persistence' => $persistence, 'access' => $access, 'fields' => $fields, 'rows' => $rows];
    }

    return [
        'tables' => $tables,
        'source' => $pdo->query('SELECT id,label FROM public.ir_ct_source ORDER BY id NULLS FIRST,label COLLATE "C"')->fetchAll(PDO::FETCH_NUM),
        'marker' => $pdo->query('SELECT value FROM public.ir_ct_marker ORDER BY value')->fetchAll(PDO::FETCH_COLUMN),
    ];
}

function observeCreateAs(PDO $pdo, array $input): array
{
    $schema = '(?:(?:"public"|"ir_ct_decoy"|public|ir_ct_decoy)\.)?';
    $target = $schema . '(?:"ir_ct_target"|ir_ct_target)';
    $pattern = '/\ACREATE (?:UNLOGGED )?TABLE (?:IF NOT EXISTS )?' . $target . '(?=[ (]).* AS (?:SELECT|VALUES)\b/sD';
    $fixture = $input['fixture'];
    $sql = $input['sql'];
    if (str_contains($sql, ';') || preg_match($pattern, $sql) !== 1 || !in_array($fixture, ['absent', 'table', 'view'], true)) {
        return ['status' => 'outside-observer-scope'];
    }
    resetCreateAsFixture($pdo, $fixture);
    $original = createAsCatalog($pdo);
    $pdo->beginTransaction();
    $pdo->exec("SET LOCAL search_path = public; SET LOCAL default_table_access_method = heap; SET LOCAL default_tablespace = ''");
    $profile = $pdo->query(
        "SELECT json_build_array(current_schema()::text,current_setting('search_path'),current_schemas(true)::text[],"
        . "current_setting('default_table_access_method'),current_setting('default_tablespace'))",
    )->fetchColumn();
    $pdo->exec('INSERT INTO public.ir_ct_marker VALUES (7)');
    $pdo->exec('UPDATE public.ir_ct_source SET id = 12 WHERE id = 8');
    $before = createAsCatalog($pdo);
    $oldTarget = $pdo->query("SELECT to_regclass('public.ir_ct_target')::oid")->fetchColumn();
    $effect = observeCreateAsStep($pdo, $sql);
    $after = createAsCatalog($pdo);
    $newTarget = $pdo->query("SELECT to_regclass('public.ir_ct_target')::oid")->fetchColumn();
    $write = observeCreateAsStep($pdo, 'INSERT INTO public.ir_ct_target DEFAULT VALUES');
    $pdo->exec('UPDATE public.ir_ct_source SET id = 21 WHERE id = 12');
    $later = createAsCatalog($pdo);
    $active = $pdo->inTransaction();
    $pdo->rollBack();

    return [
        'status' => 'create-table-as-effect', 'objectScope' => json_decode($profile, true, 512, JSON_THROW_ON_ERROR),
        'original' => $original, 'before' => $before, 'effect' => $effect, 'after' => $after,
        'preserved' => $oldTarget === null ? null : $oldTarget === $newTarget, 'write' => $write, 'later' => $later,
        'active' => $active, 'restored' => createAsCatalog($pdo),
    ];
}

resetCreateAsFixture($pdo, 'table');
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => createAsCatalog($pdo)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $responses = array_map(static fn (array $item): array => observeCreateAs($pdo, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
