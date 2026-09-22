<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration;

/**
 * Categories of session state that PostgreSQL DISCARD can release.
 * @visibility public
 * @example Choosing a mode
 *     \SqlSemantics\Model\Configuration\DiscardResource::All->value // => 'ALL'
 */
enum DiscardResource: string
{
    case All = 'ALL';
    case Plans = 'PLANS';
    case Sequences = 'SEQUENCES';
    case TemporaryTables = 'TEMPORARY';
}
