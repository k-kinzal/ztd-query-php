<?php

declare(strict_types=1);

require '/stage/vendor/autoload.php';

$roles = json_decode(file_get_contents('/roles.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (array_keys($roles) as $name) {
    $role = new ReflectionClass($name);
    $path = $role->getFileName();
    if ($path === false) {
        throw new RuntimeException('An installed role has no source file.');
    }
    echo json_encode([
        'probe' => 'role',
        'name' => $role->getName(),
        'kind' => $role->isInterface() ? 'interface' : ($role->isEnum() ? 'enum' : 'class'),
        'final' => $role->isFinal(),
        'path' => $path,
        'sha256' => hash_file('sha256', $path),
    ], JSON_THROW_ON_ERROR) . "\n";
}

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
    $occurrence = $platform === 'Sqlite' ? 'source' : 'occurrence';
    foreach ($reference->columns as $column) {
        $expression = $column->expression;
        if ([$expression::class, $expression->slot ?? null, $expression->$occurrence ?? null] !== [$namespace . 'Ir\\ColumnReference', 0, 0]) {
            throw new RuntimeException('The installed analyzer resolved different slots for equivalent references.');
        }
    }
    if ($platform !== 'Sqlite') {
        $parameterSql = $platform === 'MySql' ? 'SELECT CAST(? AS SIGNED) AS value' : 'SELECT CAST($1 AS integer) AS value';
        $value = $semantics->analyze($parameterSql)->columns[0]->expression;
        if ($value::class !== $namespace . 'Ir\\Binding\\Parameter' || $value->position !== 1) {
            throw new RuntimeException('The installed analyzer lost the projected parameter position.');
        }
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
    $queries['Aggregation\\GroupedSelect'] = "SELECT id, COUNT(*) AS entries FROM $table GROUP BY id";
    if ($platform === 'Sqlite') {
        $queries['Rows\\ValuesQuery'] = 'VALUES (1), (2)';
        $queries['Schema\\Query\\CreateTableAs'] = 'CREATE TABLE made AS SELECT CAST(9 AS INTEGER) AS result';
    } else {
        $queries['Mutation\\InsertQuery'] = "INSERT INTO $table (id) SELECT 7 AS value";
        $queries['Rows\\LocalProjection'] = 'SELECT q.value FROM (SELECT 42 AS value) AS q';
        $queries['Rows\\RecursiveSeries'] = 'WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT t.n + 1 FROM t WHERE t.n < 3) SELECT n AS value FROM t';
        $queries['Select'] = 'SELECT 42 AS answer WHERE (NOT NULL) IS NULL';
        $predicate = $semantics->analyze($queries['Select'])->where;
        if ($predicate::class !== $namespace . 'Ir\\Predicate\\NullTest'
            || $predicate->operand::class !== $namespace . 'Ir\\Predicate\\LogicalPredicate'
            || $predicate->operand->operator->value !== 'NOT') {
            throw new RuntimeException('The installed analyzer lost the composed predicate.');
        }
    }

    foreach ($queries as $role => $sql) {
        $result = $semantics->analyze($sql, $context);
        if (!$result instanceof SqlSemantics\SemanticsIR || $result::class !== $namespace . 'Ir\\' . $role) {
            throw new RuntimeException('The installed analyzer published an unexpected role.');
        }
        if ($role === 'Schema\\Query\\CreateTableAs') {
            $columns = $result->columns();
            if (count($columns) !== 1 || $columns[0]::class !== $namespace . 'Ir\\Schema\\Query\\CreatedColumn'
                || $columns[0]->name->value !== 'result' || $columns[0]->type->value !== 'INT') {
                throw new RuntimeException('The installed CREATE TABLE AS derives an unexpected column.');
            }
        }
        echo json_encode([
            'probe' => 'publication',
            'platform' => $platform,
            'role' => $role,
            'profile' => $result->profileId(),
            'path' => (new ReflectionClass($result))->getFileName(),
            'input' => $sql,
            'sql' => $result->toString(),
        ], JSON_THROW_ON_ERROR) . "\n";
    }
}
