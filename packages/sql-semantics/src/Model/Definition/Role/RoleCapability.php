<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

/**
 * A role attribute that is granted with its keyword and withheld with the NO prefix.
 * @visibility public
 * @example Inspecting a capability
 *     \SqlSemantics\Model\Definition\Role\RoleCapability::BypassRls->value // => 'BYPASSRLS'
 */
enum RoleCapability: string
{
    case Superuser = 'SUPERUSER';
    case CreateDb = 'CREATEDB';
    case CreateRole = 'CREATEROLE';
    case Inherit = 'INHERIT';
    case Login = 'LOGIN';
    case Replication = 'REPLICATION';
    case BypassRls = 'BYPASSRLS';
}
