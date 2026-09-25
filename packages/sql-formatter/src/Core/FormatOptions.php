<?php

declare(strict_types=1);

namespace SqlFormatter\Core;

use InvalidArgumentException;

/**
 * Immutable layout choices shared by all dialects.
 *
 * @visibility public
 * @example Selecting a layout
 *     $options = new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::River, 2);
 *     $options->style->value // => 'river'
 *     $options->indentWidth // => 2
 */
final class FormatOptions
{
    /**
     * @param Style $style Layout preset
     * @param int $indentWidth Spaces per nested block, from 1 to 16
     *
     * @throws InvalidArgumentException When the indentation is outside the supported range
     */
    public function __construct(
        public readonly Style $style = Style::Expanded,
        public readonly int $indentWidth = 4,
    ) {
        if ($indentWidth < 1 || $indentWidth > 16) {
            throw new InvalidArgumentException('Indent width must be between 1 and 16.');
        }
    }
}
