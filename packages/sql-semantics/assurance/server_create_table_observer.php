<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';

$engine = $argv[1];
[$pdo, $environment] = connectFixture($engine);
$mysql = $engine === 'mysql';
$schema = $mysql ? 'ir_fixture' : 'public';
$database = $pdo->query($mysql ? 'SELECT DATABASE()' : 'SELECT current_database()')->fetchColumn();
if ($database !== 'ir_fixture') {
    throw new RuntimeException('The CREATE TABLE observer requires the disposable ir_fixture database.');
}
$target = $schema . '.ir_create_target';
$marker = $schema . '.ir_create_marker';
if ($mysql) {
    $pdo->exec("SET SESSION default_storage_engine = 'InnoDB'");
    $storage = $pdo->query('SELECT @@session.default_storage_engine')->fetchColumn();
} else {
    $pdo->exec("SET default_table_access_method = 'heap'");
    $storage = $pdo->query('SHOW default_table_access_method')->fetchColumn();
}
$pdo->exec('DROP TABLE IF EXISTS ' . $marker);
$pdo->exec('CREATE TABLE ' . $marker . ' (value INTEGER)' . ($mysql ? ' ENGINE=InnoDB' : ''));
$pdo->exec('INSERT INTO ' . $marker . ' VALUES (0)');

function admitCreateTable(string $sql, string $schema, bool $mysql): bool
{
    $quote = $mysql ? chr(96) : '"';
    $name = static fn (string $part): string => '(?:' . $quote . $part . $quote . '|' . $part . ')';
    $table = $name($schema) . '\.' . $name('ir_create_target');
    $textType = $mysql ? '' : '|TEXT|(?:pg_catalog\.)?text';
    $textLiteral = "'(?:[^'\\x00]|'')*'";
    $textDefault = $mysql ? '' : '|' . $textLiteral . '|CAST\(' . $textLiteral . ' AS pg_catalog\.text\)';
    $column = $name('c[0-9]') . ' (?:SMALLINT|INTEGER|INT|BIGINT' . $textType . ')(?: NULL| NOT NULL)?'
        . '(?: DEFAULT (?:NULL|-?[0-9]{1,10}' . $textDefault . '))?';
    $engine = $mysql ? '(?: ENGINE=InnoDB)?' : '(?: USING heap)?';

    return preg_match('/\ACREATE TABLE (?:IF NOT EXISTS )?' . $table . ' \(' . $column . '(?:, ' . $column . '){0,9}\)' . $engine . '\z/D', $sql) === 1;
}

function createDefault(string $engine, ?string $value, string $kind): int|string|null
{
    if ($value === null || preg_match('/\ANULL(?:::(?:smallint|integer|bigint|text))?\z/iD', $value) === 1) {
        return null;
    }
    if ($kind === 'text' && preg_match("/\A'((?:[^']|'')*)'::text\z/D", $value, $text) === 1) {
        return str_replace("''", "'", $text[1]);
    }
    if (preg_match("/\A[(]?'?(-?[0-9]{1,10})'?(?:::(?:smallint|integer|bigint))?[)]?\z/D", $value, $number) !== 1) {
        throw new RuntimeException($engine . ' reported an unregistered default shape: ' . $value);
    }

    return $kind === 'text' ? (string) (int) $number[1] : (int) $number[1];
}

function createdCatalog(PDO $pdo, string $schema, bool $mysql): ?array
{
    $statement = $pdo->query('SELECT ordinal_position, column_name, data_type, is_nullable, column_default'
        . " FROM information_schema.columns WHERE table_schema = '" . $schema
        . "' AND table_name = 'ir_create_target' ORDER BY ordinal_position");
    $fields = [];
    $collations = $mysql ? [] : $pdo->query("SELECT a.attname, ns.nspname || '.' || c.collname"
        . ' FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_collation c ON a.attcollation = c.oid'
        . ' JOIN pg_catalog.pg_namespace ns ON c.collnamespace = ns.oid'
        . " WHERE a.attrelid = pg_catalog.to_regclass('public.ir_create_target') AND a.attnum > 0")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($statement->fetchAll(PDO::FETCH_NUM) as [$ordinal, $column, $kind, $nullable, $default]) {
        $field = [$ordinal, $column, $kind, $nullable === 'YES', createDefault($mysql ? 'MySQL' : 'PostgreSQL', $default, $kind), $default !== null];
        $fields[] = $kind === 'text' ? [...$field, $collations[$column] ?? null] : $field;
    }
    if ($fields === []) {
        return null;
    }
    $engine = $mysql
        ? $pdo->query("SELECT engine FROM information_schema.tables WHERE table_schema = '" . $schema . "' AND table_name = 'ir_create_target'")->fetchColumn()
        : $pdo->query('SELECT pg_am.amname FROM pg_catalog.pg_class JOIN pg_catalog.pg_am ON pg_am.oid = pg_class.relam'
            . " WHERE pg_class.oid = '" . $schema . ".ir_create_target'::regclass")->fetchColumn();

    return ['engine' => $engine, 'columns' => $fields];
}

function probeDefaultValues(PDO $pdo, string $target, bool $mysql): array
{
    if (!$mysql) {
        $pdo->exec('SAVEPOINT ir_create_probe');
    }
    try {
        $pdo->exec('INSERT INTO ' . $target . ($mysql ? ' () VALUES ()' : ' DEFAULT VALUES'));
        $result = ['status' => 'inserted', 'values' => $pdo->query('SELECT * FROM ' . $target)->fetch(PDO::FETCH_NUM)];
    } catch (PDOException $error) {
        if (!$mysql) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ir_create_probe');
        }
        $result = ['status' => $error->errorInfo[0]];
    }

    return $result;
}

function createEffect(PDO $pdo, string $schema, string $target, string $marker, bool $mysql, array $input): array
{
    if (!admitCreateTable($input['sql'], $schema, $mysql)) {
        return ['status' => 'outside-observer-scope'];
    }
    $pdo->exec('DROP TABLE IF EXISTS ' . $target);
    $pdo->exec('DELETE FROM ' . $marker . ' WHERE value <> 0');
    if ($input['occupied']) {
        $pdo->exec('CREATE TABLE ' . $target . ' (prior INTEGER NOT NULL DEFAULT 97)');
    }
    $pdo->beginTransaction();
    $pdo->exec('INSERT INTO ' . $marker . ' VALUES (7)');
    try {
        $pdo->exec($input['sql']);
        $status = 'executed';
    } catch (PDOException $error) {
        $status = $error->errorInfo[0];
    }
    if ($mysql) {
        $pdo->query('SELECT 1')->fetchColumn();
    }
    $active = $pdo->inTransaction();
    $catalog = $active && $status !== 'executed' ? null : createdCatalog($pdo, $schema, $mysql);
    $values = $status === 'executed' ? probeDefaultValues($pdo, $target, $mysql) : null;
    if ($active) {
        $pdo->rollBack();
    }

    return ['status' => $status, 'active' => $active, 'catalog' => $catalog, 'defaultInsertion' => $values,
            'afterRollback' => createdCatalog($pdo, $schema, $mysql),
            'markerAfterRollback' => $pdo->query('SELECT value FROM ' . $marker . ' ORDER BY value')->fetchAll(PDO::FETCH_COLUMN)];
}

echo json_encode([
    'database' => $environment, 'php' => PHP_VERSION, 'storage' => $storage, 'marker' => $pdo->query('SELECT value FROM ' . $marker)->fetchAll(PDO::FETCH_COLUMN),
    ...($mysql ? [] : ['int4TextCastOverrides' => (int) $pdo->query("SELECT count(*) FROM pg_catalog.pg_cast WHERE castsource = 'pg_catalog.int4'::pg_catalog.regtype AND casttarget = 'pg_catalog.text'::pg_catalog.regtype")->fetchColumn()]),
], JSON_THROW_ON_ERROR) . "\n";
while (($line = fgets(STDIN)) !== false) {
    $item = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    $requests = array_is_list($item) ? $item : [$item];
    $responses = [];
    foreach ($requests as $request) {
        $responses[] = createEffect($pdo, $schema, $target, $marker, $mysql, $request);
    }
    echo json_encode(array_is_list($item) ? $responses : $responses[0], JSON_THROW_ON_ERROR) . "\n";
}
