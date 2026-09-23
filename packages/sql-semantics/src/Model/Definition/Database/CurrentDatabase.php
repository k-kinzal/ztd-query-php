<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database;

/**
 * References the session database when the supplied snapshot does not name it.
 * @visibility public
 * @example Inspecting the policy
 *     \SqlSemantics\Model\Definition\Database\CurrentDatabase::Session->name // => 'Session'
 */
enum CurrentDatabase
{
    case Session;
}
