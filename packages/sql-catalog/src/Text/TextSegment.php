<?php

declare(strict_types=1);

namespace SqlCatalog\Text;

/**
 * One piece of a partially reconstructed string.
 *
 * The hierarchy is sealed: a segment is either text the analyzer knows
 * (`LiteralText`) or a gap it does not (`TextHole`).
 *
 * @visibility root
 */
interface TextSegment
{
    /**
     * The segment rendered for humans, with gaps shown as a placeholder marker.
     */
    public function display(): string;
}
