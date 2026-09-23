<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database;

/**
 * Selects whether the database accepts modification requests.
 * @visibility public
 * @example Inspecting the policy
 *     \SqlSemantics\Model\Definition\Database\DatabaseReadOnly::Disabled->value // => '0'
 */
enum DatabaseReadOnly: string
{
    case Enabled = '1';
    case Disabled = '0';
}
