<?php

declare(strict_types=1);

$installed = $argv[1] ?? '/stage';
$catalog = $argv[2] ?? '/roles.json';
require $installed . '/vendor/autoload.php';

$roles = json_decode(file_get_contents($catalog), true, 512, JSON_THROW_ON_ERROR);
function observeEnum(string $name): array
{
    $reflection = new ReflectionEnum($name);
    if ($reflection->getBackingType()?->getName() !== 'string') {
        throw new RuntimeException('An installed IR enum has an unexpected backing type.');
    }
    $listed = $name::cases();
    $cases = [];
    foreach ($reflection->getCases() as $position => $declaration) {
        $case = $declaration->getValue();
        $direct = constant($name . '::' . $case->name);
        $selected = $name::from($case->value);
        $optional = $name::tryFrom($case->value);
        $restored = unserialize(serialize($case), ['allowed_classes' => [$name]]);
        if ($case !== $direct || $case !== ($listed[$position] ?? null) || $case !== $selected
            || $case !== $optional || $case !== $restored) {
            throw new RuntimeException('An installed enum publication route returned another value.');
        }
        $cases[] = [$case->name, $case->value];
    }
    if (count($listed) !== count($cases)) {
        throw new RuntimeException('An installed enum exposes an extra case.');
    }
    $unknown = '__ztd_invalid_enum_value';
    while ($name::tryFrom($unknown) !== null) {
        $unknown .= '_';
    }
    try {
        $name::from($unknown);
    } catch (ValueError) {
        return ['backing' => 'string', 'cases' => $cases, 'unknownValue' => $unknown, 'unknownRejected' => true];
    }

    throw new RuntimeException('An installed enum accepts an unknown backing value.');
}

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
        'enum' => $role->isEnum() ? observeEnum($name) : null,
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
    $declarations = ["CREATE TABLE $table (id INTEGER)"];
    if ($platform === 'Sqlite') {
        $declarations[] = "CREATE VIEW main.removable AS SELECT id FROM $table";
    }
    $context = $semantics->context($declarations);
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
        $queries['Schema\\DropView'] = 'DROP VIEW IF EXISTS main.removable';
        $queries['Schema\\CreateTable'] = 'CREATE TABLE other (id INTEGER PRIMARY KEY, code TEXT COLLATE NOCASE UNIQUE CONSTRAINT "ref id" CHECK(id), UNIQUE(code COLLATE BINARY DESC) ON CONFLICT IGNORE)';
        $queries['Schema\\Query\\CreateTableAs'] = 'CREATE TABLE made AS SELECT CAST(9 AS INTEGER) AS result';
        $queries['Schema\\View\\CreateView'] = 'CREATE VIEW view_result("true") AS SELECT id FROM accounts';
        $queries['Schema\\Alter\\RenameTable'] = "ALTER TABLE $table RENAME TO renamed";
        $queries['Schema\\Alter\\RenameColumn'] = "ALTER TABLE $table RENAME COLUMN ID TO renamed";
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

    if ($platform === 'MySql') {
        $queries['Select'] = 'SELECT CAST(42 AS SIGNED) AS answer, CAST(-1 AS UNSIGNED) AS bound WHERE (NOT NULL) IS NULL';
    }
    if ($platform === 'PostgreSql') {
        $queries['Select'] = 'SELECT CAST(CAST(42 AS bool) AS integer) AS answer, CAST(CAST(2147483648 AS BIGINT) AS INTEGER) AS narrow, -9223372036854775808 AS exact WHERE (NOT NULL) IS NULL';
    }
    foreach ($queries as $role => $sql) {
        $result = $semantics->analyze($sql, $context);
        if (!$result instanceof SqlSemantics\SemanticsIR || $result::class !== $namespace . 'Ir\\' . $role) {
            throw new RuntimeException('The installed analyzer published an unexpected role.');
        }
        $details = [];
        if ($platform === 'MySql' && $role === 'Select') {
            $value = $result->columns[0]->expression;
            if ($value::class !== $namespace . 'Ir\\Expression\\SignedCast'
                || $value->operand::class !== $namespace . 'Ir\\IntegerLiteral') {
                throw new RuntimeException('The installed projection lost the signed conversion.');
            }
            $details['signedCast'] = $value->operand->decimal;
            $unsigned = $result->columns[1]->expression;
            if ($unsigned::class !== $namespace . 'Ir\\Expression\\UnsignedCast'
                || $unsigned->operand::class !== $namespace . 'Ir\\IntegerLiteral') {
                throw new RuntimeException('The installed projection lost the unsigned conversion.');
            }
            $details['unsignedCast'] = $unsigned->operand->decimal;
        }
        if ($platform === 'PostgreSql' && $role === 'Select') {
            $castRole = $namespace . 'Ir\\Expression\\BuiltinCast';
            $value = $result->columns[0]->expression;
            if ($value::class !== $castRole || $value->operand::class !== $castRole
                || $value->operand->operand::class !== $namespace . 'Ir\\IntegerLiteral') {
                throw new RuntimeException('The installed projection lost an explicit scalar conversion.');
            }
            $details['nestedCast'] = [$value->target->value, $value->operand->target->value, $value->operand->operand->decimal];
            $wide = $result->columns[1]->expression;
            $exact = $result->columns[2]->expression;
            $wideRole = $namespace . 'Ir\\Expression\\WideIntegerLiteral';
            if ($wide::class !== $castRole || $wide->operand::class !== $castRole
                || $wide->operand->operand::class !== $wideRole || $exact::class !== $wideRole) {
                throw new RuntimeException('The installed projection lost a bigint operand or result.');
            }
            $details['wideCast'] = [$wide->target->value, $wide->operand->target->value, $wide->operand->operand->decimal, $exact->decimal];
            $bindings = $semantics->analyze('SELECT $2::integer AS a0, $1::bigint AS a1, $1::integer AS a2', $context)->columns;
            $int4 = $bindings[0]->expression;
            $int8 = $bindings[1]->expression;
            $narrow = $bindings[2]->expression;
            $parameterRole = $namespace . 'Ir\\Binding\\Parameter';
            if ($int4::class !== $parameterRole || $int8::class !== $parameterRole || $narrow::class !== $castRole
                || $narrow->operand::class !== $parameterRole) {
                throw new RuntimeException('The installed projection lost the distinction between a binding and conversion.');
            }
            $details['typedParameters'] = [
                [$int4->position, $int4->input->value], [$int8->position, $int8->input->value],
                [$narrow->operand->position, $narrow->operand->input->value, $narrow->target->value],
            ];
        }
        if ($platform === 'Sqlite' && $role === 'Select') {
            $joined = $semantics->analyze('WITH source(id) AS (VALUES (?02), (3)) SELECT a.id, b.id FROM source a LEFT JOIN source b ON a.id=b.id');
            if ($joined->source::class !== $namespace . 'Ir\\Rows\\LocalSource'
                || $joined->columns[0]->expression::class !== $namespace . 'Ir\\ColumnReference'
                || $joined->columns[1]->expression::class !== $namespace . 'Ir\\ColumnReference') {
                throw new RuntimeException('Installed SQLite join lost its local definition or references.');
            }
            $details['joinedLocal'] = [
                $joined->source === $joined->joins[0]->source, $joined->columns[0]->expression->source,
                $joined->columns[1]->expression->source, substr_count($joined->toString(), '?02'),
            ];
            $selection = $semantics->analyze('SELECT IFNULL(NULL, ?007) AS chosen');
            $expression = $selection->columns[0]->expression;
            if ($expression::class !== $namespace . 'Ir\\Expression\\Nulls\\Coalesce'
                || $expression->arguments[0]::class !== $namespace . 'Ir\\Expression\\Nulls\\CoalesceArgument'
                || $expression->arguments[0]->expression::class !== $namespace . 'Ir\\NullLiteral'
                || $expression->arguments[1]->expression::class !== $namespace . 'Ir\\Parameter') {
                throw new RuntimeException('The installed first non-NULL scalar lost its ordered operand roles.');
            }
            $details['coalesce'] = [count($expression->arguments), $expression->arguments[1]->expression->label, $selection->toString()];
        }
        if ($platform === 'Sqlite' && $role === 'Schema\\CreateTable') {
            $details['keyLayout'] = array_map(static fn ($key): array => [
                $key instanceof SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey ? 'primary' : 'unique',
                $key->inline, $key->terms[0]->slot, $key->terms[0]->descending, $key->terms[0]->collation->value,
                $key->conflict?->value,
            ], $result->keys);
            $implicit = $semantics->analyze('CREATE TABLE bounded (a INTEGER CHECK (a ==00))', $context)->checks[0];
            $presentation = $implicit->label;
            $comparison = $implicit->condition;
            if ($presentation::class !== $namespace . 'Ir\\Schema\\Predicate\\ImplicitCheckLabel'
                || $comparison::class !== $namespace . 'Ir\\Predicate\\Comparison'
                || $comparison->left::class !== $namespace . 'Ir\\ColumnReference') {
                throw new RuntimeException('The installed analyzer lost the implicit CHECK role.');
            }
            $details['implicitCheck'] = [$presentation->columnName->value, $presentation->token->value, $presentation->quoted,
                $presentation->beforeOperator, $presentation->afterOperator, $presentation->leadingZeroes,
                $comparison->operator->value, $comparison->left->slot];
            $details['namedChecks'] = array_map(static function ($check) use ($namespace): array {
                if ($check::class !== $namespace . 'Ir\\Schema\\Predicate\\CheckConstraint'
                    || $check->condition::class !== $namespace . 'Ir\\ColumnReference' || $check->condition->source !== 0) {
                    throw new RuntimeException('The installed table condition lost its constraint or reference role.');
                }
                return [$check->label->value, $check->column, $check->condition->slot];
            }, $result->checks);
            $folded = $semantics->analyze('CREATE TABLE folded (code INTEGER, CONSTRAINT chk CHECK ((SELECT ?07 + ?1 + ?007) NOT IN ()))')->checks[0]->condition;
            if ($folded::class !== $namespace . 'Ir\\Predicate\\FoldedMembership' || !$folded->matches) {
                throw new RuntimeException('The installed declaration lost the folded empty membership.');
            }
            $details['foldedCheckBindings'] = array_column($folded->bindings, 'label');
            $generated = $semantics->analyze('CREATE TABLE generation (recorded INTEGER GENERATED ALWAYS AS (computed) STORED, source INTEGER, computed INTEGER AS (source))');
            $details['generatedColumns'] = [];
            foreach ($generated->columns as $slot => $field) {
                $rule = $field->generated;
                if ($rule === null) {
                    continue;
                }
                if ($rule::class !== $namespace . 'Ir\\Schema\\Generation\\ColumnGeneration'
                    || $rule->mode::class !== $namespace . 'Ir\\Schema\\Generation\\Storage'
                    || $rule->expression::class !== $namespace . 'Ir\\ColumnReference' || $rule->expression->source !== 0) {
                    throw new RuntimeException('Installed SQLite generation lost a resolved slot or storage role.');
                }
                $details['generatedColumns'][] = [$slot, $rule->mode->value, $rule->expression->slot];
            }
        }
        if ($role === 'Schema\\View\\CreateView') {
            $shadow = $semantics->context(['CREATE TABLE main.accounts (id INTEGER)', 'CREATE TEMP TABLE accounts (id TEXT)'], true);
            $view = $semantics->analyze($sql, $shadow);
            if ($view::class !== $namespace . 'Ir\\Schema\\View\\CreateView' || $view->query::class !== $namespace . 'Ir\\Select'
                || $view->query->source === null || $view->query->source::class !== $namespace . 'Ir\\TableSource') {
                throw new RuntimeException('Installed SQLite view lost the checked definition and external table occurrence.');
            }
            $details['createView'] = [$view->schema->value, $view->view->value, array_column($view->columns, 'value'),
                [$view->query->source->schema->value, $view->query->source->table->value], $view->ifNotExists];
            $groupContext = $semantics->context(['CREATE TABLE main.accounts (id INTEGER, code TEXT)'], true);
            $group = $semantics->analyze('CREATE VIEW view_group AS SELECT id, count(code) AS n FROM accounts GROUP BY id', $groupContext);
            if ($group::class !== $namespace . 'Ir\\Schema\\View\\CreateView'
                || $group->query::class !== $namespace . 'Ir\\Aggregation\\GroupedSelect'
                || $group->query->columns[1]->value::class !== $namespace . 'Ir\\Aggregation\\Count'
                || $group->query->columns[1]->value->argument === null) {
                throw new RuntimeException('Installed SQLite grouped view lost its checked aggregate and grouping roles.');
            }
            $details['groupedView'] = [array_column($group->columns, 'value'), $group->query->keys[0]->column->slot,
                $group->query->columns[1]->value->argument->slot, $group->query->keys[0]->collation->value, $group->profileId()];
            $merged = $semantics->analyze('CREATE VIEW view_set(selected) AS VALUES(4),(4) UNION ALL SELECT 2 AS item');
            if ($merged::class !== $namespace . 'Ir\\Schema\\View\\CreateView'
                || $merged->query::class !== $namespace . 'Ir\\Rows\\Compound'
                || $merged->query->first::class !== $namespace . 'Ir\\Rows\\ValuesQuery'
                || $merged->query->steps[0]->query::class !== $namespace . 'Ir\\Select') {
                throw new RuntimeException('Installed SQLite compound view lost its checked arm roles.');
            }
            $details['compoundView'] = [array_column($merged->columns, 'value'), $merged->query->steps[0]->operator->value, $merged->toString()];
            $localView = $semantics->analyze('CREATE VIEW view_local AS WITH pair(n) AS (VALUES(4),(9)) SELECT a.n AS first, b.n AS second FROM pair a CROSS JOIN pair b');
            if ($localView::class !== $namespace . 'Ir\\Schema\\View\\CreateView'
                || $localView->query::class !== $namespace . 'Ir\\Select'
                || $localView->query->source === null || $localView->query->source::class !== $namespace . 'Ir\\Rows\\LocalSource') {
                throw new RuntimeException('Installed SQLite view lost its local source role.');
            }
            $details['localView'] = [array_column($localView->columns, 'value'),
                $localView->query->source === $localView->query->joins[0]->source,
                $localView->query->columns[0]->expression->source, $localView->query->columns[1]->expression->source,
                substr_count($localView->toString(), 'WITH ')];
        }
        if ($role === 'Schema\\DropView') {
            $details['dropView'] = [$result->schema->value, $result->view->value, $result->ifExists];
        }
        if ($role === 'Schema\\Alter\\RenameTable') {
            $details['rename'] = [$result->schema->value, $result->table->value, $result->destination->value];
        }
        if ($role === 'Schema\\Alter\\RenameColumn') {
            $details['rename'] = [$result->schema->value, $result->table->value, $result->column->value,
                $result->destination->value, $result->quoteReferences];
        }
        if ($role === 'Schema\\Query\\CreateTableAs') {
            $columns = $result->columns();
            if (count($columns) !== 1 || $columns[0]::class !== $namespace . 'Ir\\Schema\\Query\\CreatedColumn') {
                throw new RuntimeException('The installed CREATE TABLE AS derives an unexpected column.');
            }
            $details['column'] = [$columns[0]->name->value, $columns[0]->type->value];
        }
        echo json_encode([
            'probe' => 'publication',
            'platform' => $platform,
            'role' => $role,
            'profile' => $result->profileId(),
            'path' => (new ReflectionClass($result))->getFileName(),
            'input' => $sql,
            'sql' => $result->toString(),
            ...$details,
        ], JSON_THROW_ON_ERROR) . "\n";
    }
}
