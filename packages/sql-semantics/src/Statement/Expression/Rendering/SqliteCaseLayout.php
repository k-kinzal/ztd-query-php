<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Rendering;

use SqlSemantics\Statement\Validation\Check;

/**
 * CASE delimiters and trivia that can affect an expression-derived output name.
 * No base, branch, result, or other expression SQL is retained.
 * @visibility public
 * @example Choosing case without introducing a projection alias
 *     $layout = new \SqlSemantics\Statement\Expression\Rendering\SqliteCaseLayout('case', 'end');
 *     $layout->start(null) // => 'case'
 */
final class SqliteCaseLayout
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The only accepted words delimit the actual CASE operation.
     */
    public function __construct(public readonly string $caseKeyword = 'CASE', public readonly string $endKeyword = 'END', public readonly string $baseGap = ' ', public readonly string $endGap = ' ')
    {
        Check::input(\SqlSemantics\Statement\Identifier\Ascii::upper($caseKeyword) === 'CASE' && \SqlSemantics\Statement\Identifier\Ascii::upper($endKeyword) === 'END', 'A CASE spelling contains only its actual delimiters.');
        Check::input((new SqliteTrivia())->accepts($baseGap) && (new SqliteTrivia())->accepts($endGap), 'CASE gaps contain only complete trivia.');
    }

    /**
     * Writes the base exactly once when this is a simple CASE.
     */
    public function start(?string $base): string
    {
        return $base === null ? $this->caseKeyword : SqliteKeywordSpacing::join($this->caseKeyword, $this->baseGap, $base);
    }

    /**
     * Closes the constructed body without merging its final operand with END.
     */
    public function finish(string $body): string
    {
        return SqliteKeywordSpacing::join($body, $this->endGap, $this->endKeyword);
    }
}
