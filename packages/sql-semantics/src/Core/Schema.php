<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use InvalidArgumentException;
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
     * @throws InvalidArgumentException When supplied state violates its invariants
     */
    public function __construct(
        public readonly Dialect $dialect,
        public readonly array $tables,
        public readonly string $defaultSchema,
        public readonly string $grammarVersion,
    ) {
        Schema\Invariant::members($tables, TableDefinition::class);
        Schema\Invariant::ensure($grammarVersion !== '', 'A schema needs a resolved grammar release.');
        $keys = [];
        foreach ($tables as $table) {
            $key = $dialect->platform()->schema()->tableKey($table);
            Schema\Invariant::ensure(!isset($keys[$key]), 'A schema must not contain duplicate table identities.');
            $keys[$key] = true;
            Schema\Invariant::table($table, $dialect);
        }
    }
    /**
     * Replaces state tables while preserving this snapshot and its language context.
     * @throws InvalidArgumentException When the replacement state violates its invariants
     */
    public function withTables(TableDefinition ...$tables): self
    {
        return new self($this->dialect, array_values($tables), $this->defaultSchema, $this->grammarVersion);
    }
}
