<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

use Override;

/**
 * Declared Sqlite insertion behavior; values remain unevaluated.
 *
 * @visibility public
 */
final class SqliteInsertion implements InsertPolicy
{
    /**
     * Records the selected policy.
     */
    public function __construct(public readonly ConstraintResponse $onViolation = ConstraintResponse::Default)
    {
    }

    /**
     * Returns the policy's dialect.
     */
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::Sqlite;
    }
}
