<?php

declare(strict_types=1);

namespace Tests\Statement;

use LogicException;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Platform\Sqlite\Schema\ColumnParser;
use SqlFixture\Platform\Sqlite\Schema\TypeDeclaration;
use SqlFixture\Schema\ColumnDefinition as SchemaColumn;
use SqlFixture\Schema\TypeShape;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Operation;

/**
 * Analyzes SQLite CREATE TABLE statements for the schema reader tests.
 */
final class SqliteStatements
{
    private static ?Semantics $semantics = null;

    /**
     * Returns the analysis of the default release, loaded once for every test.
     */
    public static function semantics(): Semantics
    {
        return self::$semantics ??= new Semantics(Dialect::Sqlite);
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

        return $statement->columns;
    }

    /**
     * Reads the type of every column in a table of the given columns.
     *
     * @return list<TypeShape>
     * @throws LogicException
     */
    public static function shapes(string $columns): array
    {
        [$operation, $statement] = self::analyzed("CREATE TABLE t ({$columns})");
        $declared = (new CreateTableOperation())->columns($operation);
        $shapes = [];
        foreach ($statement->columns as $column) {
            $domain = $declared[$column->name->value]->type;
            if (!$domain instanceof ColumnDomain) {
                throw new LogicException('A SQLite column has a column domain.');
            }
            $shapes[] = (new TypeDeclaration())->shape($domain, $column->type);
        }

        return $shapes;
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
        foreach ($statement->columns as $written) {
            $column = (new ColumnParser())->parse($written, $declared[$written->name->value], $primaryKeys);
            $columns[$column->name] = $column;
        }

        return $columns;
    }
}
