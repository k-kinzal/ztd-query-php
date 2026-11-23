<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

[$pdo, $environment] = connectFixture('postgres');
if ($pdo->query('SELECT current_database()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The view removal observer requires the disposable ir_fixture database.');
}

function resetRemovalFixture(PDO $pdo, string $kind, bool $dependent): void
{
    $pdo->exec('DROP SCHEMA IF EXISTS ir_rv_decoy CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS public.ir_rv_input, public.ir_rv_marker CASCADE');
    $pdo->exec('CREATE SCHEMA ir_rv_decoy');
    $pdo->exec('CREATE TABLE public.ir_rv_input (value integer)');
    $pdo->exec('INSERT INTO public.ir_rv_input VALUES (NULL), (3), (3), (9)');
    $pdo->exec('CREATE TABLE public.ir_rv_marker (value integer)');
    $pdo->exec('INSERT INTO public.ir_rv_marker VALUES (0)');
    foreach (['public.ir_rv_a' => 0, 'public.ir_rv_b' => 17, 'ir_rv_decoy.ir_rv_a' => 61] as $name => $offset) {
        $pdo->exec('CREATE ' . $kind . ' ' . $name . ' AS SELECT value + ' . $offset . ' AS value FROM public.ir_rv_input');
    }
    if ($dependent) {
        $pdo->exec('CREATE VIEW public.ir_rv_child AS SELECT value * 2 AS value FROM public.ir_rv_a');
        $pdo->exec('CREATE MATERIALIZED VIEW public.ir_rv_grandchild AS SELECT value + 1 AS value FROM public.ir_rv_child');
    }
    $pdo->exec('UPDATE public.ir_rv_input SET value = 17 WHERE value = 9');
}

function admitViewRemoval(string $sql): bool
{
    $optionalSchema = '(?:(?:"public"|public|"ir_rv_decoy"|ir_rv_decoy)\.)?';
    $name = 'ir_rv_(?:a|b|missing)';
    $target = $optionalSchema . '(?:' . $name . '|"' . $name . '")';

    return preg_match('/\ADROP (?:MATERIALIZED )?VIEW (?:IF EXISTS )?' . $target . '(?:, ' . $target . '){0,3}(?: RESTRICT| CASCADE)?\z/D', $sql) === 1;
}

function removalCatalog(PDO $pdo): array
{
    $catalog = $pdo->query(<<<'SQL'
        SELECT n.nspname AS schema, c.relname AS name, c.relkind AS kind
        FROM pg_catalog.pg_class AS c
        JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
        WHERE n.nspname IN ('public', 'ir_rv_decoy')
        AND c.relname IN ('ir_rv_input', 'ir_rv_marker', 'ir_rv_a', 'ir_rv_b', 'ir_rv_child', 'ir_rv_grandchild')
        ORDER BY n.nspname COLLATE "C", c.relname COLLATE "C"
        SQL)->fetchAll(PDO::FETCH_NUM);
    foreach ($catalog as &$object) {
        [$schema, $name] = $object;
        $rows = $pdo->query('SELECT value FROM ' . $schema . '.' . $name . ' ORDER BY value NULLS FIRST');
        $type = $rows->getColumnMeta(0);
        $object[] = [$type['name'], $type['native_type'], $type['pgsql:oid']];
        $object[] = $rows->fetchAll(PDO::FETCH_COLUMN);
    }
    unset($object);

    return $catalog;
}

function observeViewRemoval(PDO $pdo, array $input): array
{
    if (!in_array($input['kind'], ['VIEW', 'MATERIALIZED VIEW'], true) || !admitViewRemoval($input['sql'])) {
        return ['status' => 'outside-observer-scope'];
    }
    resetRemovalFixture($pdo, $input['kind'], $input['dependent']);
    $before = removalCatalog($pdo);
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO public.ir_rv_marker VALUES (7)');
    try {
        $pdo->exec($input['sql']);
        $result = ['dropped'];
    } catch (PDOException $exception) {
        $result = ['db-error', $exception->errorInfo[0]];
    }
    $active = $pdo->inTransaction();
    $effect = $result === ['dropped'] ? removalCatalog($pdo) : null;
    if ($active) {
        $pdo->rollBack();
    }

    return [
        'status' => 'view-removal',
        'before' => $before,
        'result' => $result,
        'active' => $active,
        'effect' => $effect,
        'restored' => removalCatalog($pdo),
    ];
}

resetRemovalFixture($pdo, 'MATERIALIZED VIEW', true);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => removalCatalog($pdo)], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($input);
    $responses = array_map(static fn (array $request): array => observeViewRemoval($pdo, $request), $batch ? $input : [$input]);
    echo json_encode($batch ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
