<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules;

/**
 * The noise token positions of the productions of definition and administration commands.
 *
 * Each entry names a production and the positions of its noise tokens, with
 * the reason. Nothing else may be skipped by the token correspondence check.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DefinitionNoise
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
