<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Plan;

use Override;

/**
 * MySQL plan format and statement execution selection.
 * @visibility public
 */
final class MySqlPlan implements PlanOptions
{
    /**
     * Rejects formats that cannot report execution measurements.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly MySqlFormat $format = MySqlFormat::Default,
        public readonly bool $analyze = false,
        public readonly bool $extended = false,
        public readonly bool $partitions = false,
    ) {
        if ($analyze && !in_array($format, [MySqlFormat::Default, MySqlFormat::Tree], true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('MySQL EXPLAIN ANALYZE requires the TREE format.');
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
