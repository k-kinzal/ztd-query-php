<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the table family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class TableNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [];
    }
}
