<?php

declare(strict_types=1);

namespace Tests\Statement;

use LogicException;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Platform\MySql\Schema\ColumnParser;
use SqlFixture\Schema\ColumnDefinition as SchemaColumn;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Statement\Operation;

/**
 * Analyzes MySQL CREATE TABLE statements for the schema reader tests.
 */
final class MySqlStatements
{
    private static ?Semantics $semantics = null;

    /**
     * Returns the analysis of the default release, loaded once for every test.
     */
    public static function semantics(): Semantics
    {
        return self::$semantics ??= new Semantics(Dialect::MySql);
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

        return array_values(array_filter($statement->elements, static fn (object $element): bool => $element instanceof ColumnDefinition));
    }

    /**
     * Returns the data types written in a table of the given columns.
     *
     * @return list<TypeName>
     * @throws LogicException
     */
    public static function types(string $columns): array
    {
        return array_map(static fn (ColumnDefinition $column): TypeName => $column->specification->dataType(), self::columns($columns));
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
        $columns = [];
        foreach ($statement->elements as $element) {
            if ($element instanceof ColumnDefinition) {
                $column = (new ColumnParser())->parse($element, $declared[$element->name->column->value], $primaryKeys);
                $columns[$column->name] = $column;
            }
        }

        return $columns;
    }
}
