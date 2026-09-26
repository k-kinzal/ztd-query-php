<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use function assert;

use SqlParser\MySql\SqlMode;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\MySql\Role\ExprForm;
use SqlSemantics\Statement\Model\MySql\Role\IdentForm;

/**
 * Spells the names, literals, and operators of MySQL expressions for the release and mode of the language.
 *
 * @visibility SqlSemantics
 */
trait Expressions
{
    /**
     * Spells the parts of a qualified name; after a `.`, the lexer reads any word as a name.
     *
     * @param list<string> $parts
     * @return list<IdentForm>
     */
    protected function qualified(array $parts): array
    {
        $names = [];
        foreach ($parts as $index => $part) {
            $value = $this->name('ident', $part, $index === 0 ? '' : '.');
            assert($value instanceof IdentForm);
            $names[] = $value;
        }

        return $names;
    }    /**
     * Answers the `sql_mode` the language reads under.
     */
    protected function mode(): SqlMode
    {
        $mode = $this->language->mode;

        return $mode instanceof Mode ? $mode->sqlMode : new SqlMode();
    }
    /**
     * Reports whether the release predates the 8.0 query grammar.
     */
    protected function legacy(): bool
    {
        return str_starts_with($this->language->version, 'mysql-5.');
    }
    /**
     * Reads a literal spelling as an expression.
     */
    protected function literal(string $text): ExprForm
    {
        $value = $this->leaf('simple_expr', $text);
        assert($value instanceof ExprForm);

        return $value;
    }
    /**
     * Applies the unary minus to a literal when the value is negative.
     */
    protected function signed(bool $negative, ExprForm $literal): ExprForm
    {
        if (!$negative) {
            return $literal;
        }
        $value = $this->form('simple_expr', ['-', 'simple_expr'], [$this->expect($literal, 'simple_expr', 'A negated literal')]);
        assert($value instanceof ExprForm);

        return $value;
    }
    /**
     * Builds a binary expression form, parenthesizing operands that bind more weakly than their positions.
     *
     * @param list<string> $symbols
     */
    protected function infix(string $rule, array $symbols, Element $left, Element $operator, Element $right): ExprForm
    {
        $value = $this->form($rule, $symbols, [$this->operand($left, $rule, $symbols, 0), $operator, $this->operand($right, $rule, $symbols, 2)]);
        assert($value instanceof ExprForm);

        return $value;
    }
}
