<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

[$pdo, $environment] = connectFixture('postgres');
if ($pdo->query('SELECT current_database()')->fetchColumn() !== 'ir_fixture') {
    throw new RuntimeException('The view declaration observer requires the disposable ir_fixture database.');
}

function resetViewDeclaration(PDO $pdo, bool $existing): void
{
    $pdo->exec('DROP SCHEMA IF EXISTS ir_cv_decoy CASCADE');
    $pdo->exec('DROP VIEW IF EXISTS public.ir_cv_view');
    $pdo->exec('DROP TABLE IF EXISTS public.ir_cv_input, public.ir_cv_marker');
    $pdo->exec('CREATE SCHEMA ir_cv_decoy');
    $pdo->exec('CREATE TABLE public.ir_cv_input (id integer, label text)');
    $pdo->exec("INSERT INTO public.ir_cv_input VALUES (NULL,'null'), (3,'same'), (3,'same'), (8,'changed')");
    $pdo->exec('CREATE TABLE public.ir_cv_marker (value integer)');
    $pdo->exec('INSERT INTO public.ir_cv_marker VALUES (0)');
    $pdo->exec("CREATE VIEW ir_cv_decoy.ir_cv_view AS SELECT 73 AS id, CAST('decoy' AS text) AS label");
    if ($existing) {
        $pdo->exec("CREATE VIEW public.ir_cv_view WITH (security_invoker=true) AS SELECT 37 AS id, CAST('prior' AS text) AS label");
    }
}

function admitDeclaration(string $sql): bool
{
    $schema = '(?:(?:"public"|"ir_cv_decoy"|public|ir_cv_decoy)\.)?';
    $target = '(?:"ir_cv_view"|ir_cv_view)';

    return !str_contains($sql, ';') && preg_match('/\ACREATE (?:OR REPLACE )?VIEW ' . $schema . $target
        . '(?=[ (]).* AS (?:SELECT|VALUES)\b/sD', $sql) === 1;
}

function viewOptionTypes(array $rows): array
{
    $options = ['securityBarrier' => null, 'securityInvoker' => null, 'checkOption' => ''];
    foreach ($rows as $row) {
        [$key, $value] = explode('=', $row, 2);
        if ($key === 'check_option') {
            $options['checkOption'] = strtoupper($value);
            continue;
        }
        $name = match ($key) {
            'security_barrier' => 'securityBarrier',
            'security_invoker' => 'securityInvoker',
        };
        $options[$name] = match ($value) {
            'true', 'on', 'yes', '1' => true,
            'false', 'off', 'no', '0' => false,
        };
    }

    return $options;
}

function viewDeclarationState(PDO $pdo): array
{
    $catalog = $pdo->query(<<<'SQL'
        SELECT n.nspname, c.oid, c.relkind, v.is_updatable, v.is_insertable_into, v.check_option
        FROM pg_catalog.pg_class AS c
        JOIN pg_catalog.pg_namespace AS n ON n.oid = c.relnamespace
        JOIN information_schema.views AS v ON v.table_schema = n.nspname AND v.table_name = c.relname
        WHERE c.relname = 'ir_cv_view' AND n.nspname IN ('public', 'ir_cv_decoy')
        ORDER BY n.nspname
        SQL)->fetchAll(PDO::FETCH_NUM);
    $views = [];
    $oids = [];
    foreach ($catalog as [$schema, $oid, $kind, $updatable, $insertable, $check]) {
        $statement = $pdo->query('SELECT * FROM ' . $schema . '.ir_cv_view');
        $fields = [];
        for ($i = 0; $i < $statement->columnCount(); ++$i) {
            $column = $statement->getColumnMeta($i);
            $fields[] = [$column['name'], $column['native_type'], $column['pgsql:oid']];
        }
        $options = $pdo->query('SELECT unnest(reloptions) FROM pg_catalog.pg_class WHERE oid = ' . (int) $oid)->fetchAll(PDO::FETCH_COLUMN);
        $views[$schema] = [
            'kind' => $kind, 'updatable' => $updatable === 'YES', 'insertable' => $insertable === 'YES', 'check' => $check,
            'options' => viewOptionTypes($options), 'fields' => $fields, 'rows' => $statement->fetchAll(PDO::FETCH_NUM),
        ];
        $oids[$schema] = $oid;
    }

    return [
        'state' => [
            'views' => $views,
            'base' => $pdo->query('SELECT id, label FROM public.ir_cv_input ORDER BY id NULLS FIRST, label COLLATE "C"')->fetchAll(PDO::FETCH_NUM),
            'marker' => $pdo->query('SELECT value FROM public.ir_cv_marker ORDER BY value')->fetchAll(PDO::FETCH_COLUMN),
        ],
        'oids' => $oids,
    ];
}

function attemptDeclarationAction(PDO $pdo, string $sql, string $label): array
{
    $pdo->exec('SAVEPOINT ir_cv_action');
    try {
        $pdo->exec($sql);
        $result = [$label];
    } catch (PDOException $error) {
        $result = ['db-error', $error->errorInfo[0]];
        $pdo->exec('ROLLBACK TO SAVEPOINT ir_cv_action');
    }
    $pdo->exec('RELEASE SAVEPOINT ir_cv_action');

    return $result;
}

function observeDeclaration(PDO $pdo, array $request): array
{
    if (!admitDeclaration($request['sql'])) {
        return ['status' => 'outside-observer-scope'];
    }
    resetViewDeclaration($pdo, $request['existing']);
    $before = viewDeclarationState($pdo);
    $pdo->beginTransaction();
    $pdo->exec('SET LOCAL search_path = public');
    $scope = $pdo->query(
        "SELECT json_build_array(current_schema()::text, current_setting('search_path'), current_schemas(true)::text[])",
    )->fetchColumn();
    $creationScope = json_decode($scope, true, 512, JSON_THROW_ON_ERROR);
    $pdo->exec('INSERT INTO public.ir_cv_marker VALUES (7)');
    $result = attemptDeclarationAction($pdo, $request['sql'], 'created');
    $created = viewDeclarationState($pdo);
    $write = null;
    if ($request['write'] && $result === ['created']) {
        $write = attemptDeclarationAction($pdo, "INSERT INTO public.ir_cv_view VALUES (-7, 'hidden')", 'written');
    }
    $pdo->exec('UPDATE public.ir_cv_input SET id = 11 WHERE id = 8');
    $afterUpdate = viewDeclarationState($pdo);
    $active = $pdo->inTransaction();
    $pdo->rollBack();
    $restored = viewDeclarationState($pdo);
    $identity = isset($before['oids']['public'], $created['oids']['public'])
        ? $before['oids']['public'] === $created['oids']['public'] : null;

    return [
        'status' => 'view-declaration', 'creationScope' => $creationScope, 'result' => $result, 'write' => $write, 'active' => $active, 'sameIdentity' => $identity,
        'before' => $before['state'], 'created' => $created['state'], 'afterUpdate' => $afterUpdate['state'],
        'restored' => $restored['state'],
    ];
}

resetViewDeclaration($pdo, true);
echo json_encode(['database' => $environment, 'php' => PHP_VERSION, 'initial' => viewDeclarationState($pdo)['state']], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $batch = array_is_list($input);
    $responses = array_map(static fn (array $item): array => observeDeclaration($pdo, $item), $batch ? $input : [$input]);
    echo json_encode($batch ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
