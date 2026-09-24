<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

use Override;

/**
 * Declared Sqlite insertion behavior; values remain unevaluated.
 *
 * @visibility public
 * @example Reading SQLite insertion options
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT OR REPLACE INTO t VALUES(1)');
 *     $statement->policy->onViolation // => \SqlSemantics\Model\Write\Policy\ConstraintResponse::Replace
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
