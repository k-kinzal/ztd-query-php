<?php

declare(strict_types=1);

namespace Tests\Fixture\Platform;

use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use RuntimeException;
use SqlFixture\Platform\MySql\MySqlColumnReader;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Hands a test the parts of a CREATE TABLE statement the parser produces.
 *
 * A column reader is asked about one definition at a time, and the only
 * practical way to obtain one is to let the parser read a statement, so that
 * reading lives here rather than in every test that needs a definition.
 */
final class MySqlDefinition
{
    /**
     * Reads the first column of a statement, the way the parser hands it over.
     *
     * @param string $sql Statement as it was written
     *
     * @return ColumnDefinition|null The column, or null when the definition declares no type
     *
     * @throws RuntimeException When the SQL is not a CREATE TABLE statement with a named first column
     */
    public static function firstColumnOf(string $sql): ?ColumnDefinition
    {
        $field = self::definitionOf($sql, 0);
        $name = $field->name;
        if (!is_string($name)) {
            throw new RuntimeException("First definition has no name: {$sql}");
        }

        return (new MySqlColumnReader())->read($field, $name, []);
    }

    /**
     * Answers one definition of a statement, counted as it was written.
     *
     * @param string $sql Statement as it was written
     * @param int $index Which definition to answer for
     *
     * @return CreateDefinition The definition the parser produced
     *
     * @throws RuntimeException When the statement declares no such definition
     */
    public static function definitionOf(string $sql, int $index): CreateDefinition
    {
        $fields = self::statementOf($sql)->fields;
        if (!is_array($fields) || !isset($fields[$index])) {
            throw new RuntimeException("No definition {$index} in: {$sql}");
        }

        return $fields[$index];
    }

    /**
     * Answers the CREATE TABLE statement the parser reads out of the SQL.
     *
     * @param string $sql Statement as it was written
     *
     * @return CreateStatement The statement the parser produced
     *
     * @throws RuntimeException When the SQL is not a CREATE TABLE statement
     */
    public static function statementOf(string $sql): CreateStatement
    {
        return self::parserOf($sql)[1];
    }

    /**
     * Answers the parser that read a statement, and the statement it produced.
     *
     * The parser has to be kept as well as its result, because what it says
     * about the reading is part of what a caller may be asking about.
     *
     * @param string $sql Statement as it was written
     *
     * @return array{Parser, CreateStatement} The parser, and the statement it produced
     *
     * @throws RuntimeException When the SQL is not a CREATE TABLE statement
     */
    public static function parserOf(string $sql): array
    {
        $parser = new Parser($sql);
        $statement = $parser->statements[0] ?? null;
        if (!$statement instanceof CreateStatement) {
            throw new RuntimeException("Not a CREATE TABLE statement: {$sql}");
        }

        return [$parser, $statement];
    }
}
