<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

/**
 * Dialect-specific insertion behavior beyond the required row source.
 *
 * @visibility public
 * @example Reading a policy's dialect
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t VALUES(1)');
 *     $statement->policy->dialect() // => \SqlSemantics\Dialect::PostgreSql
 */
interface InsertPolicy
{
    /**
     * Identifies the language in which this policy is meaningful.
     */
    public function dialect(): \SqlSemantics\Dialect;
}
