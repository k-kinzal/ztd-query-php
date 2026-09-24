<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

/**
 * Grants or withholds one role capability; NOLOGIN is the withheld form of LOGIN.
 * @visibility public
 * @example Reading a withheld capability
 *     $attribute = new \SqlSemantics\Model\Definition\Role\RoleAttribute(\SqlSemantics\Model\Definition\Role\RoleCapability::Login, false);
 *     $attribute->granted // => false
 */
final class RoleAttribute
{
    /**
     * Retains the capability and its direction without applying it to a role.
     */
    public function __construct(public readonly RoleCapability $capability, public readonly bool $granted)
    {
    }
}
