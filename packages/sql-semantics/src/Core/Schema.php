<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use SqlSemantics\Core\Schema\TableDefinition;

/**
 * A closed schema snapshot. Missing declarations are errors, not invented tables.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Schema $value): string => $value::class;
 *     $consume instanceof \Closure // => true
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
