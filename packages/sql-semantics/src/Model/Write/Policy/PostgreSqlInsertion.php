<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

use Override;

/**
 * Declared PostgreSql insertion behavior; values remain unevaluated.
 *
 * @visibility public
 */
final class PostgreSqlInsertion implements InsertPolicy
{
    /**
     * Records the selected policy.
     */
    public function __construct(public readonly IdentityOverride $overriding = IdentityOverride::Default)
    {
    }

    /**
     * Returns the policy's dialect.
     */
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::PostgreSql;
    }
}
