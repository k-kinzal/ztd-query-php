<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * PostgreSQL plan reporting options with their execution prerequisites.
 * @visibility public
 * @example Reading PostgreSQL plan options
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $options = $binder->bind('EXPLAIN (ANALYZE, WAL, FORMAT JSON) SELECT 1')->options;
 *     $options instanceof \SqlSemantics\Model\Plan\PostgreSqlPlan // => true
 *     $options->wal // => true
 *     $options->dialect() // => \SqlSemantics\Dialect::PostgreSql
 *     new \SqlSemantics\Model\Plan\PostgreSqlPlan(wal: true) // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class PostgreSqlPlan implements PlanOptions
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly bool $analyze = false,
        public readonly bool $verbose = false,
        public readonly bool $costs = true,
        public readonly bool $settings = false,
        public readonly bool $genericPlan = false,
        public readonly bool $buffers = false,
        public readonly SerializationCost $serialization = SerializationCost::None,
        public readonly bool $wal = false,
        public readonly ?bool $timing = null,
        public readonly ?bool $summary = null,
        public readonly bool $memory = false,
        public readonly PostgreSqlFormat $format = PostgreSqlFormat::Text,
    ) {
        if ($analyze && $genericPlan || !$analyze && ($wal || $timing === true || $serialization !== SerializationCost::None)) {
            throw new InvalidStructure('EXPLAIN execution measurements require ANALYZE, and ANALYZE excludes GENERIC_PLAN.');
        }
    }

    /**
     * Returns the SQL dialect that defines these options.
     */
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::PostgreSql;
    }
}
