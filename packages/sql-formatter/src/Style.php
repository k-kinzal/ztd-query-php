<?php

declare(strict_types=1);

namespace SqlFormatter;

/**
 * The four supported layouts. All preserve the spelling and order of SQL tokens.
 *
 * @example Naming a preset
 *     \SqlFormatter\Style::Expanded->value // => 'expanded'
 *
 * @visibility public
 */
enum Style: string
{
    case Compact = 'compact';
    case Expanded = 'expanded';
    case Tabular = 'tabular';
    case River = 'river';
}
