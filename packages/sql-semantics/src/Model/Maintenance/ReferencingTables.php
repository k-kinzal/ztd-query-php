<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance;

/**
 * Whether truncation requires referencing tables to be listed or includes them transitively.
 * @visibility public
 * @example Reading the policy
 *     \SqlSemantics\Model\Maintenance\ReferencingTables::RequireListed->value // => 'RESTRICT'
 */
enum ReferencingTables: string
{
    case RequireListed = 'RESTRICT';
    case Include = 'CASCADE';
}
