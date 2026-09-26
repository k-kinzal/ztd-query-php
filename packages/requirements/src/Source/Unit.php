<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Model\Source;

/**
 * A complete unit of source text at a stable location, such as a paragraph or a line.
 *
 * @visibility public
 *
 * @example Normalizing whitespace the way quotations are compared
 *     \Requirements\Source\Unit::normalize("  Names\n\tstart  with a letter. ") // => 'Names start with a letter.'
 * @example Identifying a unit within its source
 *     $unit = new \Requirements\Source\Unit('line:1', 'Names start with a letter.');
 *     strlen($unit->key(new \Requirements\Model\Source('notes', 'notes.txt', 'text', 'lines:1'))) // => 64
 */
final class Unit
{
    /**
     * @param string $location The stable location of the unit within its source
     * @param string $text The complete normalized text of the unit
     */
    public function __construct(public readonly string $location, public readonly string $text)
    {
    }

    /**
     * Identifies the unit by its source URI, format and location.
     *
     * @param Source $source The source the unit belongs to
     *
     * @return string The SHA-256 hex digest identifying the unit
     */
    public function key(Source $source): string
    {
        return hash('sha256', $source->uri . "\0" . $source->format . "\0" . $this->location);
    }

    /**
     * Collapses whitespace, including no-break spaces, and trims the text.
     *
     * @param string $text The text
     *
     * @return string The normalized text
     */
    public static function normalize(string $text): string
    {
        return trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $text) ?? $text);
    }
}
