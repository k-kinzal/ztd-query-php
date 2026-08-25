<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * Reads a CREATE TABLE statement as a table.
 *
 * Each dialect writes declarations its own way, so each brings its own reader;
 * what they all answer with is the same.
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
