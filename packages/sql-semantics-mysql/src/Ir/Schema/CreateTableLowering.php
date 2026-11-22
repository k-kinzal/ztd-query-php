<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;

/**
 * Resolves a native persistent CREATE TABLE.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateTableLowering
{
    /**
     * Resolves the declaration location and the ordered column definitions.
     *
     * @throws InvalidConstruction When the statement has no complete native declaration role
     */
    public function lower(BoundStatement $operation, ParsedCreateTable $statement): CreateTable
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input(
            $profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings()),
            'CREATE TABLE requires the default native profile.'
        );
        Check::input(
            !$statement->temporary() && $statement->query === null && $statement->partitioning === null && !$statement->enclosed,
            'CREATE TABLE requires a persistent ordinary table with explicit columns.'
        );
        $engine = $statement->options[0] ?? null;
        Check::input($statement->options === [] || (count($statement->options) === 1 && $engine instanceof EngineOption
            && strcasecmp($engine->engine->value, 'InnoDB') === 0), 'CREATE TABLE requires the native InnoDB storage role.');
        $schema = ($statement->name->schema ?? $context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED, 'CREATE TABLE requires a named declaration schema.');
        $name = new TableName(new Identifier($schema), new Identifier($statement->name->name->value));
        $columns = [];
        $lowering = new CreateColumnLowering();
        foreach ($statement->elements as $column) {
            Check::input($column instanceof ColumnDefinition, 'CREATE TABLE requires ordinary column definitions.');
            $columns[] = $lowering->lower($operation, $column);
        }

        return new CreateTable($name, $columns, $statement->ifNotExists, $profile->grammar);
    }
}
