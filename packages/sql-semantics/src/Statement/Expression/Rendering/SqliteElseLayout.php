<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Rendering;

use SqlSemantics\Statement\Validation\Check;

/**
 * ELSE keyword spelling and its bounded gaps, independent of the fallback expression.
 * @visibility public
 * @example Retaining a lowercase fallback delimiter
 *     (new \SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout('else'))->append('CASE WHEN 1 THEN 2', '3') // => 'CASE WHEN 1 THEN 2 else 3'
 */
final class SqliteElseLayout
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Only complete trivia can separate ELSE from its actual operands.
     */
    public function __construct(public readonly string $keyword = 'ELSE', public readonly string $before = ' ', public readonly string $after = ' ')
    {
        Check::input(strtoupper($keyword) === 'ELSE', 'An ELSE spelling contains only its actual keyword.');
        Check::input((new SqliteTrivia())->accepts($before) && (new SqliteTrivia())->accepts($after), 'ELSE gaps contain only complete trivia.');
    }

    /**
     * Retains a supplied fallback without synthesizing an ELSE for an omitted one.
     */
    public function append(string $branches, string $otherwise): string
    {
        return SqliteKeywordSpacing::join(SqliteKeywordSpacing::join($branches, $this->before, $this->keyword), $this->after, $otherwise);
    }
}
