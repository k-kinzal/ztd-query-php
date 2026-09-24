<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation;

/**
 * Selects every trigger of a table, or only user-defined triggers, instead of one by name.
 * @visibility public
 * @example Reading the SQL spelling
 *     \SqlSemantics\Model\Definition\Relation\TriggerGroup::User->value // => 'USER'
 */
enum TriggerGroup: string
{
    case All = 'ALL';
    case User = 'USER';
}
