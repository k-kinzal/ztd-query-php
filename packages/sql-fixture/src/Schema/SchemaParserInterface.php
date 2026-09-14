<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * Parses CREATE TABLE text into a dialect-independent schema.
 */
interface SchemaParserInterface
{
    /**
     * Parse a CREATE TABLE statement into a TableSchema.
     *
     * @throws SchemaParseException
     */
    public function parse(string $createTableSql): TableSchema;
}
