<?php

declare(strict_types=1);

require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_varchar_write.php';

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
    $pdo->exec("SET SESSION sql_notes = 1, max_error_count = 64, lc_messages = 'en_US'");
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
    $textType = $mysql ? '|VARCHAR\([0-9]{1,5}\)(?: CHARACTER SET utf8mb4)?(?: COLLATE utf8mb4_(?:bin|general_ci))?'
        : '|TEXT|(?:pg_catalog\.)?text|BOOLEAN|(?:pg_catalog\.)?bool';
    $textLiteral = $mysql ? <<<'PATTERN'
'(?:\\[\s\S]|''|[^'\\])*'
PATTERN
        : <<<'PATTERN'
(?:'(?:[^'\x00]|'')*'|E'(?:\\[\s\S]|''|[^'\\\x00])*')
PATTERN;
    $textDefault = '|' . $textLiteral . ($mysql ? '' : '|CAST\(' . $textLiteral . ' AS pg_catalog\.text\)|CAST\(-?[0-9]{1,19} AS pg_catalog\.int8\)'
        . '|TRUE|FALSE|CAST\((?:TRUE|FALSE|NULL|' . $textLiteral . ') AS pg_catalog\.bool\)');
    $signed = $mysql ? 'TINYINT|MEDIUMINT|' : '';
    $slot = $name($mysql ? '[cC](?:[0-9]|1[0-6])' : 'c[0-9]');
    $column = $slot . ' (?:' . $signed . 'SMALLINT|INTEGER|INT|BIGINT' . $textType . ')(?: NULL| NOT NULL)?'
        . '(?: DEFAULT (?:NULL|-?[0-9]{1,19}' . $textDefault . '))?' . ($mysql ? '(?: PRIMARY KEY| KEY)?' : '');
    $part = $slot . '(?: ASC| DESC)?';
    $key = $mysql ? '(?:, (?:CONSTRAINT custom )?PRIMARY KEY(?: custom)? \\(' . $part . '(?:, ' . $part . '){0,15}\\))?' : '';
    $engine = $mysql ? '(?: ENGINE=InnoDB)?(?:(?: DEFAULT)?(?: CHARACTER SET utf8mb4)? COLLATE(?: =)? utf8mb4_(?:bin|general_ci))?'
        : '(?: USING heap)?';

    $extent = $mysql ? '{0,16}' : '{0,9}';

    return preg_match('/\ACREATE TABLE (?:IF NOT EXISTS )?' . $table . ' \(' . $column . '(?:, ' . $column . ')' . $extent . $key . '\)' . $engine . '\z/D', $sql) === 1;
}

function createDefault(string $engine, ?string $value, string $kind): int|string|bool|null
{
    if ($value === null || preg_match('/\ANULL(?:::(?:smallint|integer|bigint|text))?\z/iD', $value) === 1) {
        return null;
    }
    if ($kind === 'text' && preg_match("/\A'((?:[^']|'')*)'::text\z/D", $value, $text) === 1) {
        return str_replace("''", "'", $text[1]);
    }
    if ($kind === 'boolean') {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => throw new RuntimeException('PostgreSQL reported an unregistered Boolean default: ' . $value),
        };
    }
    if (preg_match("/\A[(]?'?(-?[0-9]{1,19})'?(?:::(?:smallint|integer|bigint))?[)]?(?:::bigint)?\z/D", $value, $number) !== 1) {
        throw new RuntimeException($engine . ' reported an unregistered default shape: ' . $value);
    }

    return $kind === 'text' ? (string) (int) $number[1] : (int) $number[1];
}

function createdCatalog(
    PDO $pdo,
    string $schema,
    bool $mysql,
    bool $nativeDetails = false,
    bool $primaryDetails = false,
    bool $defaultExpressions = false,
): ?array
{
    $statement = $pdo->query('SELECT ordinal_position, column_name, data_type, is_nullable, column_default, character_maximum_length, collation_name'
        . " FROM information_schema.columns WHERE table_schema = '" . $schema
        . "' AND table_name = 'ir_create_target' ORDER BY ordinal_position");
    $fields = [];
    $operands = [];
    $collations = $mysql ? [] : $pdo->query("SELECT a.attname, ns.nspname || '.' || c.collname"
        . ' FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_collation c ON a.attcollation = c.oid'
        . ' JOIN pg_catalog.pg_namespace ns ON c.collnamespace = ns.oid'
        . " WHERE a.attrelid = pg_catalog.to_regclass('public.ir_create_target') AND a.attnum > 0")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($statement->fetchAll(PDO::FETCH_NUM) as [$ordinal, $column, $kind, $nullable, $default, $length, $collation]) {
        $operands[] = $default;
        $literal = $mysql && $kind === 'varchar' ? $default : createDefault($mysql ? 'MySQL' : 'PostgreSQL', $default, $kind);
        $field = [$ordinal, $column, $kind, $nullable === 'YES', $literal, $default !== null];
        $fields[] = match ($kind) {
            'varchar' => [...$field, $length, $collation],
            'text' => [...$field, $collations[$column] ?? null],
            default => $field,
        };
    }
    if ($fields === []) {
        return null;
    }
    $engine = $mysql
        ? $pdo->query("SELECT engine FROM information_schema.tables WHERE table_schema = '" . $schema . "' AND table_name = 'ir_create_target'")->fetchColumn()
        : $pdo->query('SELECT pg_am.amname FROM pg_catalog.pg_class JOIN pg_catalog.pg_am ON pg_am.oid = pg_class.relam'
            . " WHERE pg_class.oid = '" . $schema . ".ir_create_target'::regclass")->fetchColumn();

    $layout = $nativeDetails ? $pdo->query('SELECT table_collation AS tableCollation, row_format AS rowFormat'
        . " FROM information_schema.tables WHERE table_schema = 'ir_fixture' AND table_name = 'ir_create_target'")->fetch(PDO::FETCH_ASSOC) : [];

    $primary = $primaryDetails ? $pdo->query('SELECT index_name, non_unique, seq_in_index, column_name, collation, sub_part, nullable, index_type'
        . " FROM information_schema.statistics WHERE table_schema = 'ir_fixture' AND table_name = 'ir_create_target'"
        . " AND index_name = 'PRIMARY' ORDER BY seq_in_index")->fetchAll(PDO::FETCH_NUM) : [];

    return ['engine' => $engine, 'columns' => $fields, ...$layout, ...($primaryDetails ? ['primaryIndex' => $primary] : []),
        ...($defaultExpressions ? ['defaultExpressions' => $operands] : [])];
}

function probeDefaultValues(PDO $pdo, string $target, bool $mysql, bool $nativeDetails = false): array
{
    if (!$mysql) {
        $pdo->exec('SAVEPOINT ir_create_probe');
    }
    try {
        $pdo->exec('INSERT INTO ' . $target . ($mysql ? ' () VALUES ()' : ' DEFAULT VALUES'));
        $diagnostics = $nativeDetails ? mysqlFixtureDiagnostics($pdo) : [];
        $result = ['status' => 'inserted', 'values' => $pdo->query('SELECT * FROM ' . $target)->fetch(PDO::FETCH_NUM), ...$diagnostics];
    } catch (PDOException $error) {
        if (!$mysql) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ir_create_probe');
        }
        $result = ['status' => $error->errorInfo[0]];
    }

    return $result;
}

function repeatDefaultInsertion(PDO $pdo, string $target): array
{
    try {
        $pdo->exec('INSERT INTO ' . $target . ' () VALUES ()');
        $status = 'inserted';
    } catch (PDOException $error) {
        $status = $error->errorInfo[0];
    }

    return ['status' => $status, ...mysqlFixtureDiagnostics($pdo)];
}

function explicitReplacement(PDO $pdo, string $target, array $replacement): array
{
    $columns = [];
    foreach (array_keys($replacement) as $column) {
        if (!is_string($column) || preg_match('/\A(?:c[0-9]|prior)\z/D', $column) !== 1) {
            throw new RuntimeException('Unregistered explicit replacement column.');
        }
        $columns[] = '"' . $column . '"';
    }
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $statement = $pdo->prepare('INSERT INTO ' . $target . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ') RETURNING *');
    $statement->execute(array_values($replacement));

    return $statement->fetch(PDO::FETCH_NUM);
}

function createEffect(PDO $pdo, string $schema, string $target, string $marker, bool $mysql, array $input): array
{
    if (!admitCreateTable($input['sql'], $schema, $mysql)) {
        return ['status' => 'outside-observer-scope'];
    }
    $nativeDetails = $mysql && ($input['observeNativeDeclaration'] ?? false) === true;
    $primaryDetails = $nativeDetails && ($input['observePrimaryIndex'] ?? false) === true;
    $signedDefaults = !$mysql && isset($input['explicitReplacement']);
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
    $diagnostics = $nativeDetails ? mysqlFixtureDiagnostics($pdo) : [];
    if ($mysql) {
        $pdo->query('SELECT 1')->fetchColumn();
    }
    $active = $pdo->inTransaction();
    $catalog = $active && $status !== 'executed' ? null : createdCatalog($pdo, $schema, $mysql, $nativeDetails, $primaryDetails, $signedDefaults);
    $values = $status === 'executed' ? probeDefaultValues($pdo, $target, $mysql, $nativeDetails) : null;
    $repeat = $primaryDetails && $status === 'executed' ? repeatDefaultInsertion($pdo, $target) : null;
    $explicit = $signedDefaults && $status === 'executed' ? explicitReplacement($pdo, $target, $input['explicitReplacement']) : null;
    if ($active) {
        $pdo->rollBack();
    }

    $after = createdCatalog($pdo, $schema, $mysql, $nativeDetails, $primaryDetails, $signedDefaults);
    $stored = ($nativeDetails || $signedDefaults) && $after !== null ? $pdo->query('SELECT * FROM ' . $target)->fetchAll(PDO::FETCH_NUM) : [];

    return ['status' => $status, 'active' => $active, 'catalog' => $catalog, 'defaultInsertion' => $values,
            'afterRollback' => $after, ...$diagnostics, ...($nativeDetails || $signedDefaults ? ['stored' => $stored] : []),
            ...($primaryDetails ? ['secondInsertion' => $repeat] : []),
            ...($signedDefaults ? ['explicitReplacement' => $explicit] : []),
            'markerAfterRollback' => $pdo->query('SELECT value FROM ' . $marker . ' ORDER BY value')->fetchAll(PDO::FETCH_COLUMN)];
}

echo json_encode([
    'database' => $environment, 'php' => PHP_VERSION, 'storage' => $storage, 'marker' => $pdo->query('SELECT value FROM ' . $marker)->fetchAll(PDO::FETCH_COLUMN),
    ...($mysql ? [
        'schemaCollation' => $pdo->query("SELECT default_collation_name FROM information_schema.schemata WHERE schema_name = 'ir_fixture'")->fetchColumn(),
        'storageSettings' => $pdo->query('SELECT @@innodb_page_size, @@innodb_strict_mode, @@sql_notes, @@max_error_count, @@lc_messages')->fetch(PDO::FETCH_NUM),
    ] : []),
    ...($mysql ? [] : [
        'int4TextCastOverrides' => (int) $pdo->query("SELECT count(*) FROM pg_catalog.pg_cast WHERE castsource = 'pg_catalog.int4'::pg_catalog.regtype AND casttarget = 'pg_catalog.text'::pg_catalog.regtype")->fetchColumn(),
        'int8TextCastOverrides' => (int) $pdo->query("SELECT count(*) FROM pg_catalog.pg_cast WHERE castsource = 'pg_catalog.int8'::pg_catalog.regtype AND casttarget = 'pg_catalog.text'::pg_catalog.regtype")->fetchColumn(),
        'signedAssignmentCasts' => $pdo->query("SELECT castsource::pg_catalog.regtype::text, casttarget::pg_catalog.regtype::text, castcontext, castfunc::pg_catalog.regprocedure::text"
            . " FROM pg_catalog.pg_cast WHERE castsource IN ('pg_catalog.int4'::pg_catalog.regtype, 'pg_catalog.int8'::pg_catalog.regtype)"
            . " AND casttarget IN ('pg_catalog.int2'::pg_catalog.regtype, 'pg_catalog.int4'::pg_catalog.regtype, 'pg_catalog.int8'::pg_catalog.regtype) ORDER BY 1, 2")->fetchAll(PDO::FETCH_NUM),
    ]),
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
