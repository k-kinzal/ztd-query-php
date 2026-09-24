<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

use Override;

/**
 * Declared PostgreSql insertion behavior; values remain unevaluated.
 *
 * @visibility public
 * @example Reading PostgreSQL insertion options
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t OVERRIDING USER VALUE VALUES(1)');
 *     $statement->policy->overriding // => \SqlSemantics\Model\Write\Policy\IdentityOverride::User
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
