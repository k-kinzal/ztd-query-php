<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlSemantics\Schema\TableDefinition;

/**
 * A closed schema snapshot. Missing declarations are errors, not invented tables.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $schema->dialect->value // => 'postgresql'
 *
 * @visibility public
 */
final class Schema
{
    /**
     * @param Dialect $dialect Language used to interpret the declarations
     * @param list<TableDefinition> $tables Declarations visible to semantic binding
     * @param string $defaultSchema Schema used for unqualified table declarations and references
     * @param string $grammarVersion Resolved sql-parser grammar release shared by DDL and SELECT
     */
    public function __construct(
        public readonly Dialect $dialect,
        public readonly array $tables,
        public readonly string $defaultSchema,
        public readonly string $grammarVersion,
    ) {
    }
}
