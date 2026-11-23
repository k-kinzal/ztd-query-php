<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

[$pdo, $environment] = connectFixture('postgres');
if ($pdo->query('SELECT current_database()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The materialized view observer requires the disposable ir_fixture database.');
}

function resetMaterializedFixture(PDO $pdo, string $fixture): void
{
    $pdo->exec('DROP SCHEMA IF EXISTS ir_mv_decoy CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS public.ir_mv_input CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS public.ir_mv_marker');
    $pdo->exec('CREATE SCHEMA ir_mv_decoy');
    $pdo->exec('CREATE TABLE public.ir_mv_input (id integer, label text)');
    $pdo->exec("INSERT INTO public.ir_mv_input VALUES (NULL,'null'), (3,'same'), (3,'same'), (8,'changed')");
    $pdo->exec('CREATE TABLE public.ir_mv_marker (value integer)');
    $pdo->exec('INSERT INTO public.ir_mv_marker VALUES (0)');
    $pdo->exec('CREATE MATERIALIZED VIEW ir_mv_decoy.ir_mv_view USING heap AS SELECT id + 70 AS id,label FROM public.ir_mv_input');
    if ($fixture === 'ordinary') {
        $pdo->exec('CREATE VIEW public.ir_mv_view AS SELECT id,label FROM public.ir_mv_input');
    } elseif ($fixture !== 'absent') {
        $data = $fixture === 'unpopulated' ? ' WITH NO DATA' : ' WITH DATA';
        $pdo->exec('CREATE MATERIALIZED VIEW public.ir_mv_view USING heap AS SELECT id,label FROM public.ir_mv_input' . $data);
    }
}

function admitMaterializedOperation(string $sql): bool
{
    $schema = '(?:(?:"public"|"ir_mv_decoy"|public|ir_mv_decoy)\.)?';
    $target = $schema . '(?:"ir_mv_view"|ir_mv_view)';
    if (str_contains($sql, ';')) {
        return false;
    }

    return preg_match('/\ACREATE MATERIALIZED VIEW (?:IF NOT EXISTS )?' . $target . '(?=[ (]).* AS (?:SELECT|VALUES)\b/sD', $sql) === 1
        || preg_match('/\AREFRESH MATERIALIZED VIEW ' . $target . '(?: WITH (?:NO )?DATA)?\z/D', $sql) === 1;
}

function observeSavepoint(PDO $pdo, callable $action): array
{
    if (!$pdo->inTransaction()) {
        try {
            return $action();
        } catch (PDOException $error) {
            return ['db-error', $error->errorInfo[0]];
        }
    }
    $pdo->exec('SAVEPOINT ir_mv_observe');
    try {
        $result = $action();
    } catch (PDOException $error) {
        $result = ['db-error', $error->errorInfo[0]];
        $pdo->exec('ROLLBACK TO SAVEPOINT ir_mv_observe');
    }
    $pdo->exec('RELEASE SAVEPOINT ir_mv_observe');

    return $result;
}

function materializedCatalog(PDO $pdo): array
{
    $relations = $pdo->query(<<<'SQL'
        SELECT n.nspname, c.oid, c.relkind, c.relispopulated, a.amname
        FROM pg_catalog.pg_class AS c
        JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
        LEFT JOIN pg_catalog.pg_am AS a ON a.oid = c.relam
        WHERE n.nspname IN ('public', 'ir_mv_decoy') AND c.relname = 'ir_mv_view'
        ORDER BY n.nspname
        SQL)->fetchAll(PDO::FETCH_NUM);
    $views = [];
    foreach ($relations as [$schema, $oid, $kind, $populated, $access]) {
        $fields = $pdo->query('SELECT a.attname,t.typname,CAST(t.oid AS integer) FROM pg_catalog.pg_attribute a '
            . 'JOIN pg_catalog.pg_type t ON a.atttypid=t.oid WHERE a.attnum > 0 AND NOT a.attisdropped '
            . 'AND a.attrelid=' . (int) $oid . ' ORDER BY a.attnum')->fetchAll(PDO::FETCH_NUM);
        $rows = observeSavepoint($pdo, static fn (): array =>
            ['rows', $pdo->query('SELECT * FROM ' . $schema . '.ir_mv_view')->fetchAll(PDO::FETCH_NUM)]);
        $views[$schema] = ['kind' => $kind, 'populated' => $populated, 'access' => $access, 'fields' => $fields, 'read' => $rows];
    }

    return [
        'views' => $views,
        'base' => $pdo->query('SELECT id,label FROM public.ir_mv_input ORDER BY id NULLS FIRST,label COLLATE "C"')->fetchAll(PDO::FETCH_NUM),
        'marker' => $pdo->query('SELECT value FROM public.ir_mv_marker ORDER BY value')->fetchAll(PDO::FETCH_COLUMN),
    ];
}

function observeMaterialization(PDO $pdo, array $input): array
{
    if (!admitMaterializedOperation($input['sql']) || !in_array($input['fixture'], ['absent', 'ordinary', 'populated', 'unpopulated'], true)) {
        return ['status' => 'outside-observer-scope'];
    }
    resetMaterializedFixture($pdo, $input['fixture']);
    $original = materializedCatalog($pdo);
    $pdo->beginTransaction();
    $pdo->exec('SET LOCAL search_path = public');
    $profile = $pdo->query(
        "SELECT json_build_array(current_schema()::text,current_setting('search_path'),current_schemas(true)::text[])",
    )->fetchColumn();
    $pdo->exec('INSERT INTO public.ir_mv_marker VALUES (7)');
    $pdo->exec('UPDATE public.ir_mv_input SET id = 12 WHERE id = 8');
    $before = materializedCatalog($pdo);
    $effect = observeSavepoint($pdo, static function () use ($pdo, $input): array {
        $pdo->exec($input['sql']);

        return ['executed'];
    });
    $materialized = materializedCatalog($pdo);
    $pdo->exec('UPDATE public.ir_mv_input SET id = 21 WHERE id = 12');
    $later = materializedCatalog($pdo);
    $active = $pdo->inTransaction();
    $pdo->rollBack();

    return [
        'status' => 'materialized-view-effect', 'objectScope' => json_decode($profile, true, 512, JSON_THROW_ON_ERROR),
        'original' => $original, 'before' => $before, 'effect' => $effect, 'after' => $materialized,
        'later' => $later, 'active' => $active, 'restored' => materializedCatalog($pdo),
    ];
}

resetMaterializedFixture($pdo, 'populated');
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => materializedCatalog($pdo)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($request);
    $responses = array_map(static fn (array $item): array => observeMaterialization($pdo, $item), $batch ? $request : [$request]);
    echo json_encode($batch ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
