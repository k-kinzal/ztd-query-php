<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

use Override;

/**
 * MySQL plan format and statement execution selection.
 * @visibility public
 * @example Reading MySQL plan options
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build());
 *     $options = $binder->bind('EXPLAIN ANALYZE FORMAT=TREE SELECT 1')->options;
 *     $options instanceof \SqlSemantics\Model\Plan\MySqlPlan // => true
 *     $options->analyze // => true
 *     $options->dialect() // => \SqlSemantics\Dialect::MySql
 *     new \SqlSemantics\Model\Plan\MySqlPlan(\SqlSemantics\Model\Plan\MySqlFormat::Json, analyze: true) // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class MySqlPlan implements PlanOptions
{
    /**
     * Rejects formats that cannot report execution measurements; INTO names the user variable, without `@`, that receives a FORMAT=JSON plan.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly MySqlFormat $format = MySqlFormat::Default,
        public readonly bool $analyze = false,
        public readonly bool $extended = false,
        public readonly bool $partitions = false,
        public readonly ?string $variable = null,
    ) {
        if ($analyze && !in_array($format, [MySqlFormat::Default, MySqlFormat::Tree], true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('MySQL EXPLAIN ANALYZE requires the TREE format.');
        }
        if ($variable !== null && ($variable === '' || $format !== MySqlFormat::Json)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('EXPLAIN INTO stores a JSON plan in one named user variable.');
        }
    }

    /**
     * Returns the SQL dialect that defines these options.
     */
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::MySql;
    }
}
