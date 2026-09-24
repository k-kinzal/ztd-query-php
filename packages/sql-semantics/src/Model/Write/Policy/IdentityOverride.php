<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

/**
 * IdentityOverride alternatives.
 *
 * @visibility public
 * @example Reading an identity override
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t OVERRIDING SYSTEM VALUE VALUES(1)');
 *     $statement->policy->overriding // => \SqlSemantics\Model\Write\Policy\IdentityOverride::System
 */
enum IdentityOverride: string
{
    case Default = '';
    case System = 'SYSTEM';
    case User = 'USER';
}
