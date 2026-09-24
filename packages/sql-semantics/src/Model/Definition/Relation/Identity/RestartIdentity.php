<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Identity;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Restarts an identity sequence at its start value or at an explicit integer.
 * @visibility public
 * @example Restarting at an explicit value
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id RESTART WITH 100');
 *     $statement->actions[0]->changes[0]->value->text // => '100'
 */
final class RestartIdentity
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?Literal $value)
    {
        if ($value !== null) {
            IdentityInvariant::integer($value);
        }
    }
}
