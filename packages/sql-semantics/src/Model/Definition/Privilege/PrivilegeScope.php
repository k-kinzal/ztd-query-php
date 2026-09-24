<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

/**
 * Global privileges (*.*) or all tables of the session's current database (*).
 * @visibility public
 * @example Inspecting the global scope
 *     \SqlSemantics\Model\Definition\Privilege\PrivilegeScope::Global->value // => '*.*'
 */
enum PrivilegeScope: string
{
    case Global = '*.*';
    case CurrentDatabase = '*';
}
