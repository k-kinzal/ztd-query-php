<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql;

/**
 * A role membership option that a grant sets or a revoke removes.
 * @visibility public
 * @example Inspecting the option
 *     \SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute::Admin->value // => 'ADMIN'
 */
enum RoleGrantAttribute: string
{
    case Admin = 'ADMIN';
    case Inherit = 'INHERIT';
    case Set = 'SET';
}
