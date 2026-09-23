<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database;

/**
 * Selects the default encryption of subsequently created tables.
 * @visibility public
 * @example Inspecting the policy
 *     \SqlSemantics\Model\Definition\Database\DatabaseEncryption::Enabled->value // => 'Y'
 */
enum DatabaseEncryption: string
{
    case Enabled = 'Y';
    case Disabled = 'N';
}
