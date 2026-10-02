<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Rendering;

/**
 * Separates a checked SQL keyword from a constructively rendered expression.
 * @visibility SqlSemantics
 */
final class SqliteKeywordSpacing
{
    /**
     * At these boundaries at least one adjoining symbol is a validated keyword.
     */
    public static function join(string $left, string $gap, string $right): string
    {
        if ($gap === '' && preg_match('/[A-Za-z0-9_$\x80-\xff]\z/', $left) === 1 && preg_match('/\A[A-Za-z0-9_$\x80-\xff]/', $right) === 1) {
            $gap = ' ';
        }
        return $left . $gap . $right;
    }
}
