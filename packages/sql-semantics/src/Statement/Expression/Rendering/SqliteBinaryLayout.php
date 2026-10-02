<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Rendering;

use SqlSemantics\Statement\Expression\SqliteBinaryOperator;
use SqlSemantics\Statement\Validation\Check;

/**
 * A binary operator's bounded spelling, needed for observable expression labels.
 * No operand SQL or statement fragment is stored here.
 * @visibility public
 * @example Retaining an operator without surrounding spaces
 *     $layout = new \SqlSemantics\Statement\Expression\Rendering\SqliteBinaryLayout(\SqlSemantics\Statement\Expression\SqliteBinaryOperator::Add, '+', '', '', false);
 *     $layout->symbol() // => '+'
 */
final class SqliteBinaryLayout
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Restricts the spelling to this exact operator and complete trivia gaps.
     */
    public function __construct(public readonly SqliteBinaryOperator $operator, public readonly string $lexeme, public readonly string $before = ' ', public readonly string $after = ' ', public readonly bool $groupOperands = true)
    {
        Check::input((new SqliteTrivia())->operator($lexeme, $operator), 'An operator spelling must encode its actual operation.');
        Check::input((new SqliteTrivia())->accepts($before) && (new SqliteTrivia())->accepts($after), 'Operator gaps contain only complete whitespace and comments.');
    }

    /**
     * Writes the validated operator and its two bounded trivia gaps.
     */
    public function symbol(): string
    {
        return $this->before . $this->lexeme . $this->after;
    }

    /**
     * Joins constructed operands without merging words or opening an SQL comment.
     */
    public function between(string $left, string $right): string
    {
        $before = $this->before;
        $after = $this->after;
        if ($before === '' && preg_match('/[A-Za-z0-9_$\x80-\xff]\z/', $left) === 1 && preg_match('/\A[A-Za-z_$\x80-\xff]/', $this->lexeme) === 1) {
            $before = ' ';
        }
        if ($after === '' && ((preg_match('/[A-Za-z_$\x80-\xff]\z/', $this->lexeme) === 1 && preg_match('/\A[A-Za-z0-9_$\x80-\xff]/', $right) === 1) || (str_ends_with($this->lexeme, '-') && str_starts_with($right, '-')))) {
            $after = ' ';
        }
        return $left . $before . $this->lexeme . $after . $right;
    }

}
