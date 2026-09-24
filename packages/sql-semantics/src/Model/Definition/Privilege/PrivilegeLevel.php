<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

/**
 * The privilege levels MySQL distinguishes when checking which privileges a GRANT or REVOKE level accepts.
 * @visibility public
 * @example Inspecting a privilege level
 *     \SqlSemantics\Model\Definition\Privilege\PrivilegeLevel::Routine->value // => 'ROUTINE'
 */
enum PrivilegeLevel: string
{
    case Global = 'GLOBAL';
    case Database = 'DATABASE';
    case Table = 'TABLE';
    case Routine = 'ROUTINE';
}
