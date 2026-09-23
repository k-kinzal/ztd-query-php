<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database;

/**
 * Requests the server character default in the legacy MySQL database grammar.
 * @visibility public
 * @example Inspecting the policy
 *     \SqlSemantics\Model\Definition\Database\ServerCharacterInheritance::Inherit->name // => 'Inherit'
 */
enum ServerCharacterInheritance
{
    case Inherit;
}
