<?php

declare(strict_types=1);

namespace Tests\Fixture;

use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use RuntimeException;
use ZtdQuery\Schema\TableDefinition;

/**
 * The statements an ALTER TABLE test works from.
 *
 * Every one of them is read by the same parser the package reads statements
 * with, so a test is working from what MySQL would actually be handed rather
 * than from a hand-built object that only resembles it.
 */
final class MySqlAlterStatements
{
    /**
     * Reads an ALTER TABLE.
     *
     * @param string $sql Statement to read
     *
     * @return AlterStatement The statement, as the parser reads it
     *
     * @throws RuntimeException When the text is not an ALTER TABLE
     */
    public static function statement(string $sql): AlterStatement
    {
        $statement = (new Parser($sql))->statements[0] ?? null;
        if (!$statement instanceof AlterStatement) {
            throw new RuntimeException("Not an ALTER TABLE: {$sql}");
        }

        return $statement;
    }

    /**
     * Reads the one operation an ALTER TABLE writes.
     *
     * @param string $sql Statement to read
     *
     * @return AlterOperation The operation, as the parser reads it
     *
     * @throws RuntimeException When the text is not an ALTER TABLE, or writes no operation
     */
    public static function operation(string $sql): AlterOperation
    {
        $operation = self::statement($sql)->altered[0] ?? null;
        if (!$operation instanceof AlterOperation) {
            throw new RuntimeException("No ALTER operation in: {$sql}");
        }

        return $operation;
    }

    /**
     * Reads the one operation an ALTER TABLE against a table called t writes.
     *
     * @param string $operationSql Operation to read, without the ALTER TABLE around it
     *
     * @return AlterOperation The operation, as the parser reads it
     *
     * @throws RuntimeException When it writes no operation
     */
    public static function operationOn(string $operationSql): AlterOperation
    {
        return self::operation('ALTER TABLE t ' . $operationSql);
    }

    /**
     * Reads a CREATE TABLE.
     *
     * @param string $sql Declaration to read
     *
     * @return CreateStatement The declaration, as the parser reads it
     *
     * @throws RuntimeException When the text is not a CREATE TABLE
     */
    public static function declaration(string $sql): CreateStatement
    {
        $statement = (new Parser($sql))->statements[0] ?? null;
        if (!$statement instanceof CreateStatement) {
            throw new RuntimeException("Not a CREATE TABLE: {$sql}");
        }

        return $statement;
    }

    /**
     * Answers the declaration the users fixture is altered from.
     *
     * @return CreateStatement A table of an id and a name, as the parser reads it
     *
     * @throws RuntimeException When the parser will not read what is written here
     */
    public static function usersDeclaration(): CreateStatement
    {
        return self::declaration('CREATE TABLE `users` (`id` INT, `name` VARCHAR(100))');
    }

    /**
     * Answers what the users fixture holds.
     *
     * @return TableDefinition A table of an id it is keyed by, and a name
     */
    public static function usersDefinition(): TableDefinition
    {
        return new TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'VARCHAR(100)'], ['id'], [], []);
    }
}
