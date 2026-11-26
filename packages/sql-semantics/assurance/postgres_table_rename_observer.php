<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

[$pdo, $environment] = connectFixture('postgres');
if ($pdo->query('SELECT current_database()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The rename observer requires the disposable ir_fixture database.');
}

function resetRenameFixture(PDO $pdo, string $fixture): void
{
    $pdo->exec('DROP SCHEMA IF EXISTS ir_rn_decoy CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS public.ir_rn_child, public.ir_rn_target, public.ir_rn_occupied, public.ir_rn_marker CASCADE');
    $pdo->exec('CREATE SCHEMA ir_rn_decoy');
    $pdo->exec('CREATE TABLE public.ir_rn_marker (step integer)');
    $pdo->exec('INSERT INTO public.ir_rn_marker VALUES (0)');
    $pdo->exec('CREATE TABLE public.ir_rn_occupied (value integer)');
    $pdo->exec('INSERT INTO public.ir_rn_occupied VALUES (91)');
    $pdo->exec('CREATE TABLE ir_rn_decoy.ir_rn_target (id integer, label text)');
    $pdo->exec("INSERT INTO ir_rn_decoy.ir_rn_target VALUES (33,'other')");
    $pdo->exec('CREATE TABLE ir_rn_decoy.ir_rn_occupied (value integer)');
    $pdo->exec('INSERT INTO ir_rn_decoy.ir_rn_occupied VALUES (92)');
    $pdo->exec('CREATE VIEW ir_rn_decoy.ir_rn_reader AS SELECT id,label FROM ir_rn_decoy.ir_rn_target');
    if ($fixture !== 'absent') {
        $pdo->exec('CREATE TABLE public.ir_rn_target (id integer, label text)');
        $pdo->exec("INSERT INTO public.ir_rn_target VALUES (1,'main'), (NULL,'unset'), (1,'main')");
        if ($fixture === 'inherited') {
            $pdo->exec('CREATE TABLE public.ir_rn_child (note text) INHERITS (public.ir_rn_target)');
            $pdo->exec("INSERT INTO public.ir_rn_child VALUES (19,'descendant','local')");
        }
        $pdo->exec('CREATE VIEW public.ir_rn_reader AS SELECT id,label FROM public.ir_rn_target');
    }
}

function renameFixtureRelations(PDO $pdo): array
{
    return $pdo->query(<<<'SQL'
        SELECT c.oid, n.nspname, c.relname, c.relkind
        FROM pg_catalog.pg_class AS c
        JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
        WHERE n.nspname IN ('public', 'ir_rn_decoy')
          AND left(c.relname, 6) = 'ir_rn_' AND c.relname <> 'ir_rn_marker'
        ORDER BY n.nspname, c.relname COLLATE "C"
        SQL)->fetchAll(PDO::FETCH_NUM);
}

function renameFixtureQuote(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function renameFixtureState(PDO $pdo, array $relations, array $identities): array
{
    $catalog = [];
    foreach ($relations as [$oid, $schema, $name, $kind]) {
        $fields = $pdo->query('SELECT a.attnum,a.attname,t.typname,CAST(t.oid AS integer)'
            . ' FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_type t ON a.atttypid=t.oid'
            . ' WHERE a.attrelid=' . (int) $oid . ' AND a.attnum > 0 AND NOT a.attisdropped ORDER BY a.attnum')->fetchAll(PDO::FETCH_NUM);
        $qualifier = renameFixtureQuote($schema) . '.' . renameFixtureQuote($name);
        $rows = $pdo->query('SELECT * FROM ' . $qualifier)->fetchAll(PDO::FETCH_NUM);
        $own = $kind === 'r' ? $pdo->query('SELECT * FROM ONLY ' . $qualifier)->fetchAll(PDO::FETCH_NUM) : null;
        $catalog[$schema . '.' . $name] = [
            'identity' => $identities[$oid] ?? null, 'kind' => $kind, 'fields' => $fields, 'rows' => $rows, 'own' => $own,
        ];
    }

    return ['catalog' => $catalog, 'marker' => $pdo->query('SELECT step FROM public.ir_rn_marker ORDER BY step')->fetchAll(PDO::FETCH_COLUMN)];
}

function renameFixtureOrigins(array $relations): array
{
    $identities = [];
    foreach ($relations as [$oid, $schema, $name]) {
        $identities[$oid] = $schema . '.' . $name;
    }
    return $identities;
}

function executeRenameFixture(PDO $pdo, string $sql): array
{
    $pdo->exec('SAVEPOINT ir_rn_observe');
    try {
        $pdo->exec($sql);
        $status = ['executed'];
    } catch (PDOException $error) {
        $status = ['db-error', $error->errorInfo[0]];
        $pdo->exec('ROLLBACK TO SAVEPOINT ir_rn_observe');
    }
    $pdo->exec('RELEASE SAVEPOINT ir_rn_observe');
    return $status;
}

function observeTableRename(PDO $pdo, array $input): array
{
    $schema = '(?:(?:"public"|"ir_rn_decoy"|public|ir_rn_decoy)\.)?';
    $name = '(?:ir_rn_target|"ir_rn_target")';
    $identifier = '(?:[a-z_][a-z_0-9]*|"(?:[^"]|"")*")';
    $pattern = '/\AALTER TABLE (?:IF EXISTS )?(?:ONLY )?' . $schema . $name . ' RENAME (?:TO ' . $identifier
        . '|(?:COLUMN )?' . $identifier . ' TO ' . $identifier . ')\z/D';
    $fixture = $input['fixture'];
    $sql = $input['sql'];
    if (str_contains($sql, ';') || preg_match($pattern, $sql) !== 1 || !in_array($fixture, ['absent', 'plain', 'inherited'], true)) {
        return ['status' => 'outside-observer-scope'];
    }
    resetRenameFixture($pdo, $fixture);
    $initial = renameFixtureRelations($pdo);
    $identities = renameFixtureOrigins($initial);
    $original = renameFixtureState($pdo, $initial, $identities);
    $pdo->beginTransaction();
    $pdo->exec('SET LOCAL search_path = public');
    $profile = $pdo->query("SELECT json_build_array(current_schema()::text,current_setting('search_path'),current_schemas(true)::text[])")->fetchColumn();
    $pdo->exec('INSERT INTO public.ir_rn_marker VALUES (7)');
    $before = renameFixtureState($pdo, renameFixtureRelations($pdo), $identities);
    $effect = executeRenameFixture($pdo, $sql);
    $after = renameFixtureState($pdo, renameFixtureRelations($pdo), $identities);
    $active = $pdo->inTransaction();
    $pdo->rollBack();

    return [
        'status' => 'table-rename-effect', 'objectScope' => json_decode($profile, true, 512, JSON_THROW_ON_ERROR),
        'original' => $original, 'before' => $before, 'effect' => $effect, 'after' => $after, 'active' => $active,
        'restored' => renameFixtureState($pdo, renameFixtureRelations($pdo), $identities),
    ];
}

resetRenameFixture($pdo, 'inherited');
$initial = renameFixtureRelations($pdo);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION,
    'initial' => renameFixtureState($pdo, $initial, renameFixtureOrigins($initial))], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $results = array_map(static fn (array $item): array => observeTableRename($pdo, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $results : $results[0], JSON_THROW_ON_ERROR) . "\n";
}
