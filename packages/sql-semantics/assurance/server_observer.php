<?php

declare(strict_types=1);

// This observer is started by the evidence process and never inside the candidate container.
$engine = $argv[1];
require __DIR__ . '/server_connection.php';
require __DIR__ . '/mysql_literal_observation.php';
require __DIR__ . '/mysql_text_collation_observation.php';
require __DIR__ . '/mysql_varchar_effect.php';
require __DIR__ . '/mysql_varchar_write.php';
require __DIR__ . '/mysql_varchar_assignment.php';
require __DIR__ . '/mysql_varchar_insert.php';
require __DIR__ . '/mysql_varchar_insert_query.php';
[$pdo, $environment] = connectFixture($engine);

echo json_encode(['database' => $environment, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";

function installViewReadFixture(PDO $pdo, array $commands, array $viewNames): array
{
    $previous = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        $definitions = [];
        foreach ($commands as $position => $sql) {
            $pdo->exec($sql);
            $warnings = $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM);
            $view = str_replace('`', '``', $viewNames[$position]);
            $definition = $pdo->query("SHOW CREATE VIEW ir_fixture.`$view`")->fetch(PDO::FETCH_NUM);
            $definitions[] = ['warnings' => $warnings, 'definition' => $definition[1]];
        }

        return ['status' => 'fixture', 'views' => $definitions];
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $previous);
    }
}

function measure(PDO $pdo, array $input, bool &$readOnly): array
{
    if (isset($input['fixture'])) {
        if ($readOnly) {
            $pdo->exec('ROLLBACK');
            $readOnly = false;
        }
        if (isset($input['observeViewFixtureWarnings'])) {
            return installViewReadFixture($pdo, $input['fixture'], $input['observeViewFixtureWarnings']);
        }
        foreach ($input['fixture'] as $sql) {
            $pdo->exec($sql);
        }
        return ['status' => 'fixture'];
    }
    if (($input['observeMysqlVarcharEffect'] ?? false) === true) {
        if ($readOnly) {
            $pdo->exec('ROLLBACK');
            $readOnly = false;
        }

        return mysqlVarcharEffect($pdo, $input['sql']);
    }
    if (($input['observeMysqlVarcharAssignment'] ?? false) === true) {
        if ($readOnly) {
            $pdo->exec('ROLLBACK');
            $readOnly = false;
        }

        return mysqlVarcharAssignment($pdo, $input['sql']);
    }
    if (($input['observeMysqlVarcharInsert'] ?? false) === true) {
        if ($readOnly) {
            $pdo->exec('ROLLBACK');
            $readOnly = false;
        }

        return mysqlVarcharInsert($pdo, $input['sql']);
    }
    if (($input['observeMysqlVarcharInsertQuery'] ?? false) === true) {
        if ($readOnly) {
            $pdo->exec('ROLLBACK');
            $readOnly = false;
        }

        return mysqlVarcharInsertQuery($pdo, $input['sql']);
    }
    if (!$readOnly) {
        $pdo->exec('START TRANSACTION READ ONLY');
        $readOnly = true;
    }
    if (isset($input['withBindings']) || isset($input['postgresSetBindings'])) {
        return measurePostgresBindings($pdo, $input, $readOnly);
    }
    $warnings = ($input['observeWarnings'] ?? false) === true;
    if ($warnings) {
        // MySQL 5.x can retain earlier diagnostics when a constant statement succeeds.
        $pdo->query('SELECT 1 FROM ir_fixture.ir_evidence_accounts LIMIT 1')->closeCursor();
    }
    $shadowRange = ($input['shadowBuiltinOperators'] ?? false) === true;
    try {
        if ($shadowRange) {
            $pdo->exec('SET LOCAL search_path = public, pg_catalog');
        }
        $bindings = $input['mysqlWithBindings'] ?? [];
        if ($bindings !== [] && ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || !array_is_list($bindings) || count($bindings) > 16
            || preg_match('/\AWITH\s/i', $input['sql']) !== 1 || str_contains($input['sql'], ';'))) {
            return ['status' => 'outside-observer-scope'];
        }
        $statement = $pdo->prepare($input['sql']);
        foreach ($bindings as $offset => $binding) {
            if (!is_int($binding) && $binding !== null) {
                return ['status' => 'outside-observer-scope'];
            }
            $statement->bindValue($offset + 1, $binding, $binding === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        }
        $statement->execute();
        $fields = [];
        for ($index = 0; $index < $statement->columnCount(); ++$index) {
            $meta = $statement->getColumnMeta($index);
            $fields[] = ($input['observePostgresTypeIds'] ?? false) === true
                ? [$meta['name'], $meta['native_type'], $meta['pgsql:oid']]
                : [$meta['name'], $meta['native_type']];
            if (($input['observeMysqlIntegerMetadata'] ?? false) === true) {
                $fields[$index][] = $meta['len'];
                $fields[$index][] = in_array('not_null', $meta['flags'], true);
            }
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);
        $statement->closeCursor();
        if ($shadowRange) {
            $pdo->exec('SET LOCAL search_path = pg_catalog, public');
        }
        $result = ['status' => 'rows', 'fields' => $fields, 'rows' => $rows];
        if ($bindings !== []) {
            $result['bindings'] = $bindings;
        }
        if (($input['observeMySqlLiterals'] ?? false) === true) {
            $result['literals'] = mysqlLiteralObservations($pdo, $input['sql']);
        }
        if (($input['observeMysqlTextCollation'] ?? false) === true) {
            $result['textMetadata'] = mysqlTextCollationObservations($pdo, $input['sql']);
        }
        if (($input['observePostgresCollation'] ?? false) === true) {
            $result['collations'] = postgresResultCollations($pdo, $input['sql'], count($fields));
        }
        if ($warnings) {
            $details = ($input['observeMysqlGroupWarnings'] ?? false) === true;
            $result['warningCount'] = warningCount($pdo, $details);
            if (is_array($result['warningCount'])) {
                $result['nativeWarnings'] = $result['warningCount'];
                $result['warningCount'] = count($result['nativeWarnings']);
            }
        }
    } catch (PDOException $error) {
        $result = ['status' => 'db-error', 'state' => $error->errorInfo[0]];
        if (($input['observeVendorCode'] ?? false) === true) {
            $result['vendorCode'] = (int) ($error->errorInfo[1] ?? 0);
        }
        $pdo->exec('ROLLBACK');
        $readOnly = false;
    }
    return $result;
}

function measurePostgresBindings(PDO $pdo, array $input, bool &$readOnly): array
{
    $bindings = $input['withBindings'] ?? $input['postgresSetBindings'];
    $prefix = isset($input['withBindings']) ? '/\AWITH\s/i' : '/\A(?:SELECT\s|VALUES\s|\()/i';
    $sql = $input['sql'];
    if (!array_is_list($bindings) || count($bindings) !== 1 || !is_int($bindings[0])
        || preg_match($prefix, $sql) !== 1 || str_contains($sql, ';')) {
        return ['status' => 'outside-observer-scope'];
    }
    $prepared = false;
    try {
        $pdo->exec('PREPARE ir_with_read AS ' . $sql);
        $prepared = true;
        $types = $pdo->query(
            'SELECT param::oid::text FROM pg_catalog.pg_prepared_statements AS prepared, '
            . 'unnest(prepared.parameter_types) WITH ORDINALITY AS t (param, slot) '
            . "WHERE prepared.name = 'ir_with_read' ORDER BY slot"
        )->fetchAll(PDO::FETCH_COLUMN);
        $statement = $pdo->query('EXECUTE ir_with_read (' . $bindings[0] . ')');
        $fields = [];
        for ($index = 0; $index < $statement->columnCount(); ++$index) {
            $meta = $statement->getColumnMeta($index);
            $fields[] = [$meta['name'], $meta['native_type'], $meta['pgsql:oid']];
        }
        $rows = $statement->fetchAll(PDO::FETCH_NUM);
        $statement->closeCursor();

        return ['status' => 'rows', 'fields' => $fields, 'rows' => $rows, 'inputOids' => $types];
    } catch (PDOException $error) {
        $pdo->exec('ROLLBACK');
        $readOnly = false;

        return ['status' => 'db-error', 'state' => $error->errorInfo[0]];
    } finally {
        if ($prepared) {
            $pdo->exec('DEALLOCATE ir_with_read');
        }
    }
}

function postgresResultCollations(PDO $pdo, string $sql, int $width): array
{
    $aliases = [];
    $observations = [];
    for ($index = 0; $index < $width; ++$index) {
        $alias = '"__ztd_column_' . $index . '"';
        $aliases[] = $alias;
        $observations[] = 'pg_catalog.pg_collation_for(' . $alias . ')';
    }
    // The generated aliases identify positions even with repeated labels. A null
    // extended row exposes the result's collation even when the query is empty.
    $statement = $pdo->query(
        'SELECT ' . implode(', ', $observations) . ' FROM (VALUES (1)) AS "__ztd_left" '
        . 'LEFT JOIN (' . $sql . ') AS "__ztd_target"(' . implode(', ', $aliases) . ') ON TRUE LIMIT 1'
    );
    $result = $statement->fetch(PDO::FETCH_NUM);
    $statement->closeCursor();
    if (!is_array($result)) {
        throw new RuntimeException('Expected a PostgreSQL collation observation for each result position.');
    }

    return $result;
}

function warningCount(PDO $pdo, bool $details = false): int|array
{
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        if ($details) {
            return $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM);
        }
        $count = $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
        if (!is_numeric($count)) {
            throw new RuntimeException('Expected a server warning count.');
        }
        return (int) $count;
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
}

$readOnly = false;
while (($line = fgets(STDIN)) !== false) {
    $input = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    if (array_is_list($input)) {
        $response = [];
        foreach ($input as $request) {
            $response[] = measure($pdo, $request, $readOnly);
        }
    } else {
        $response = measure($pdo, $input, $readOnly);
    }
    echo json_encode($response, JSON_THROW_ON_ERROR) . "\n";
}
