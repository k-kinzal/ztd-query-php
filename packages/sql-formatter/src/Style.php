<?php

declare(strict_types=1);

namespace SqlFormatter;

/**
 * Compact canonicalizes SQL; the three multiline layouts preserve token spelling.
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
