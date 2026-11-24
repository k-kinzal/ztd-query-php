<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Query;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateTableLayoutLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Ir\SelectLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;

/**
 * Resolves the target and query of a native query-backed declaration.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateTableAsLowering
{
    /**
     * Checks the declaration role and builds the same complete value as direct construction.
     *
     * @throws InvalidConstruction When the declaration has a different role
     */
    public function lower(BoundStatement $operation, ParsedCreateTable $statement): CreateTableAs
    {
        $query = $statement->query;
        Check::input(
            $query !== null && $query->query instanceof ParsedSelect && $query->duplicate === null
                && $statement->partitioning === null && !$statement->enclosed,
            'CREATE TABLE AS requires a single projection without partitions or a duplicate policy.'
        );

        $options = $statement->options;
        $engine = $options[0] ?? null;
        Check::input(
            $options === [] || (count($options) === 1 && $engine instanceof EngineOption && strcasecmp($engine->engine->value, 'InnoDB') === 0),
            'CREATE TABLE AS requires the native InnoDB storage role.'
        );
        $context = $operation->context;
        Check::input($context->relationNames === Comparison::Sensitive, 'CREATE TABLE AS requires exact relation names.');
        $schema = ($statement->name->schema ?? $context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED, 'CREATE TABLE AS requires an absolute target name.');
        $table = new TableName(new Identifier($schema), new Identifier($statement->name->name->value));
        $kind = $statement->temporary() ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable;
        [$columns, $primaryKey] = (new CreateTableLayoutLowering())->lower($statement, $context->profile->grammar, null);
        Check::input($primaryKey === null, 'Explicit CREATE TABLE AS definitions do not support a primary key.');

        return new CreateTableAs(
            new TableIdentity($table, $kind),
            (new SelectLowering())->lower($operation, $query->query),
            $statement->ifNotExists,
            $columns
        );
    }
}
