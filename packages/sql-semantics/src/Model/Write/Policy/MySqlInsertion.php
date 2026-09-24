<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

use Override;

/**
 * Declared MySql insertion behavior; values remain unevaluated.
 *
 * @visibility public
 * @example Reading MySQL insertion options
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT LOW_PRIORITY IGNORE INTO t VALUES(1)');
 *     [$statement->policy->scheduling, $statement->policy->ignore] // => [\SqlSemantics\Model\Write\Policy\Scheduling::LowPriority, true]
 */
final class MySqlInsertion implements InsertPolicy
{
    /**
     * Records the selected policy.
     * @param RowAlias|null $rowAlias MySQL 8.0.19+ name of the proposed row, allowed after VALUES or SET
     */
    public function __construct(public readonly Scheduling $scheduling = Scheduling::Default, public readonly bool $ignore = false, public readonly ?RowAlias $rowAlias = null)
    {
    }

    /**
     * Returns the policy's dialect.
     */
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::MySql;
    }
}
