<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use SqlFixture\Platform\MySql\MySqlSchemaParser as PlatformMySqlSchemaParser;

/**
 * Kept so code written against the old namespace keeps working.
 *
 * Everything it is asked it forwards to the platform reader, which is where
 * the behaviour lives.
 *
 * @deprecated Use SqlFixture\Platform\MySql\MySqlSchemaParser instead
 */
final class MySqlSchemaParser implements SchemaParserInterface
{
    private PlatformMySqlSchemaParser $parser;

    /**
     * Builds a reader that delegates to the platform one.
     */
    public function __construct()
    {
        $this->parser = new PlatformMySqlSchemaParser();
    }

    /**
     * Reads the statement as a table.
     *
     * @param string $createTableSql CREATE TABLE statement as it was written
     *
     * @return TableSchema The table the statement declares
     *
     * @throws SchemaParseException When the statement cannot be read
     */
    public function parse(string $createTableSql): TableSchema
    {
        return $this->parser->parse($createTableSql);
    }
}
