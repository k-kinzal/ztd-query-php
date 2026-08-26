<?php

declare(strict_types=1);

namespace Tests\Fixture;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use RuntimeException;
use ZtdQuery\Schema\TableDefinition;

/**
 * The LOAD DATA statements and the table a load test works against.
 */
final class MySqlLoadStatements
{
    /**
     * Reads a LOAD DATA.
     *
     * @param string $sql Statement to read
     *
     * @return LoadStatement The statement, as the parser reads it
     *
     * @throws RuntimeException When the text is not a LOAD DATA
     */
    public static function statement(string $sql): LoadStatement
    {
        $statement = (new Parser($sql))->statements[0] ?? null;
        if (!$statement instanceof LoadStatement) {
            throw new RuntimeException("Not a LOAD DATA: {$sql}");
        }

        return $statement;
    }

    /**
     * Answers what the table a load test loads into holds.
     *
     * @return TableDefinition A table of an id, a name, and a total the table works out itself
     */
    public static function definition(): TableDefinition
    {
        return new TableDefinition(
            ['id', 'name', 'total'],
            ['id' => 'INT', 'name' => 'VARCHAR(100)', 'total' => 'INT'],
            ['id'],
            [],
            [],
            [],
            [],
            [],
            ['total' => 'id * 2'],
        );
    }
}
