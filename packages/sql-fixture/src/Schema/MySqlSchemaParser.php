<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

use SqlFixture\Platform\MySql\MySqlSchemaParser as PlatformMySqlSchemaParser;

/**
 * @deprecated Use SqlFixture\Platform\MySql\MySqlSchemaParser instead
 */
final class MySqlSchemaParser implements SchemaParserInterface
{
    private PlatformMySqlSchemaParser $parser;

    public function __construct()
    {
        $this->parser = new PlatformMySqlSchemaParser();
    }

    public function parse(string $createTableSql): TableSchema
    {
        return $this->parser->parse($createTableSql);
    }
}
