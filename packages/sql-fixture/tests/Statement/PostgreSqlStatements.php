<?php

declare(strict_types=1);

namespace Tests\Statement;

use LogicException;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Platform\PostgreSql\Schema\ColumnParser;
use SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration;
use SqlFixture\Schema\ColumnDefinition as SchemaColumn;
use SqlFixture\Schema\TypeShape;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;

/**
 * Analyzes PostgreSQL statements for the schema reader tests.
 */
final class PostgreSqlStatements
{
    private static ?Semantics $semantics = null;

    /**
     * Returns the analysis of the default release, loaded once for every test.
     */
    public static function semantics(): Semantics
    {
        return self::$semantics ??= new Semantics(Dialect::PostgreSql);
    }

    /**
     * Returns the analysis of a statement and its CREATE TABLE structure.
     *
     * @return array{Operation, CreateTable}
     * @throws LogicException
     */
    public static function analyzed(string $sql): array
    {
        $operation = self::semantics()->analyze($sql);
        $statement = $operation->statement;
        if (!$statement instanceof CreateTable) {
            throw new LogicException("Not a CREATE TABLE statement: {$sql}");
        }

        return [$operation, $statement];
    }

    /**
     * Returns the column definitions written in a table of the given columns.
     *
     * @return list<ColumnDefinition>
     * @throws LogicException
     */
    public static function columns(string $columns): array
    {
        [, $statement] = self::analyzed("CREATE TABLE t ({$columns})");
        $definition = $statement->definition;
        if (!$definition instanceof ListedColumns) {
            throw new LogicException('The table lists no columns.');
        }

        return array_values(array_filter($definition->elements(), static fn (object $element): bool => $element instanceof ColumnDefinition));
    }

    /**
     * Returns the expression the only target of a SELECT list holds.
     * @throws LogicException
     */
    public static function expression(string $expression): Scalar
    {
        $select = self::semantics()->analyze("SELECT {$expression}")->statement;
        $target = $select instanceof Select ? $select->targets[0] ?? null : null;
        if (!$target instanceof ExpressionTarget) {
            throw new LogicException("Not one expression: {$expression}");
        }

        return $target->expression;
    }

    /**
     * Reads the type of every column in a table of the given columns.
     *
     * @return list<TypeShape>
     * @throws LogicException
     */
    public static function shapes(string $columns): array
    {
        [$operation] = self::analyzed("CREATE TABLE t ({$columns})");
        $declared = (new CreateTableOperation())->columns($operation);

        return array_map(static fn (ColumnDefinition $column): TypeShape => (new TypeDeclaration())->shape($declared[$column->name->value]->type, $column->type), self::columns($columns));
    }

    /**
     * Reads every column of a statement into schema columns.
     *
     * @param list<string> $primaryKeys
     * @return array<string, SchemaColumn>
     * @throws LogicException
     */
    public static function parsedColumns(string $sql, array $primaryKeys = []): array
    {
        [$operation, $statement] = self::analyzed($sql);
        $declared = (new CreateTableOperation())->columns($operation);
        $definition = $statement->definition;
        $columns = [];
        foreach ($definition instanceof ListedColumns ? $definition->elements() : [] as $element) {
            if ($element instanceof ColumnDefinition) {
                $column = (new ColumnParser())->parse($element, $declared[$element->name->value], $primaryKeys);
                $columns[$column->name] = $column;
            }
        }

        return $columns;
    }
}
