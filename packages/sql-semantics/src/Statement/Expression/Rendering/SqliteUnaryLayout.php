<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Rendering;

use SqlSemantics\Statement\Expression\SqliteUnaryOperator;
use SqlSemantics\Statement\Validation\Check;

/**
 * A prefix operator's bounded spelling, separate from its actual semantic operand.
 * @visibility public
 * @example Writing a prefix without adding an unnecessary pair of parentheses
 *     $layout = new \SqlSemantics\Statement\Expression\Rendering\SqliteUnaryLayout(\SqlSemantics\Statement\Expression\SqliteUnaryOperator::Negate, '-', '', false);
 *     $layout->write('1', false) // => '-1'
 */
final class SqliteUnaryLayout
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Only the requested operator and complete inter-symbol trivia are admissible.
     */
    public function __construct(public readonly SqliteUnaryOperator $operator, public readonly string $lexeme, public readonly string $after = ' ', public readonly bool $groupOperand = true)
    {
        Check::input(\SqlSemantics\Statement\Identifier\Ascii::upper($lexeme) === $operator->value, 'A prefix spelling must encode its actual unary operator.');
        Check::input((new SqliteTrivia())->accepts($after), 'A prefix gap contains only complete trivia.');
    }

    /**
     * Required parentheses and adjacent minus signs are checked independently of requested layout.
     */
    public function write(string $operand, bool $requiresGrouping): string
    {
        if ($this->groupOperand || $requiresGrouping) {
            $operand = '(' . $operand . ')';
        }
        $gap = $this->after;
        if ($gap === '' && $this->operator === SqliteUnaryOperator::Negate && str_starts_with($operand, '-')) {
            $gap = ' ';
        }
        return SqliteKeywordSpacing::join($this->lexeme, $gap, $operand);
    }
}
