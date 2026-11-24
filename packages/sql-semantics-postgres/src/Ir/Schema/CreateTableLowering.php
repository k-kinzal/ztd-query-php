<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKeyLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;

/**
 * Resolves a native persistent CREATE TABLE.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
        Check::input($profile->parameters === ParameterStyle::Native, 'CREATE TABLE requires the default native profile.');
        $definition = $statement->definition;
        Check::input(
            $definition instanceof ListedColumns && $definition->parents === [] && $statement->persistence === Persistence::Permanent
            && $statement->partitioning === null && $statement->options === [] && $statement->onCommit === null && $statement->tablespace === null,
            'CREATE TABLE requires a persistent ordinary table with explicit columns.'
        );
        Check::input($statement->method === null || $statement->method->value === 'heap', 'CREATE TABLE requires the native heap storage role.');
        Check::input($statement->name->catalog === null, 'CREATE TABLE requires an absolute schema location.');
        $schema = ($statement->name->schema ?? $context->declarationSchema)->value;
        $name = new TableName(new Identifier($schema), new Identifier($statement->name->name->value));
        $columns = [];
        $lowering = new CreateColumnLowering();
        $primaryKey = null;
        $keys = new PrimaryKeyLowering();
        foreach ($definition->elements as $element) {
            if ($element instanceof TablePrimaryKey) {
                $key = $keys->table($element);
            } else {
                Check::input($element instanceof ColumnDefinition, 'CREATE TABLE requires ordinary column definitions.');
                [$column, $key] = $keys->column($element);
                $columns[] = $lowering->lower($operation, $column);
            }
            if ($key !== null) {
                Check::input($primaryKey === null, 'CREATE TABLE can declare only one primary key.');
                $primaryKey = $key;
            }
        }

        return new CreateTable($name, $columns, $statement->ifNotExists, $profile->grammar, $primaryKey);
    }
}
