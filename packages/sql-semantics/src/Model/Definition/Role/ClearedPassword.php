<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

/**
 * Removes a role's password; PASSWORD NULL carries no secret to retain.
 * @visibility public
 * @example Reading a cleared password
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER ROLE r PASSWORD NULL');
 *     $statement->options[0] instanceof \SqlSemantics\Model\Definition\Role\ClearedPassword // => true
 */
final class ClearedPassword
{
    /**
     * The request has no operands beyond its presence.
     */
    public function __construct()
    {
    }
}
