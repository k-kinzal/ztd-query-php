<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use function assert;

use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\PostgreSql\Role\AExprForm;

/**
 * Spells the names, constants, and operators of PostgreSQL expressions.
 *
 * @visibility SqlSemantics
 */
trait Expressions
{
    /**
     * Reads a constant spelling as an expression.
     */
    protected function constant(string $text): AExprForm
    {
        $value = $this->leaf('AexprConst', $text);
        assert($value instanceof AExprForm);

        return $value;
    }    /**
     * Applies the unary minus to a literal when the value is negative.
     */
    protected function signed(bool $negative, AExprForm $literal): AExprForm
    {
        if (!$negative) {
            return $literal;
        }
        $value = $this->form('a_expr', ['-', 'a_expr'], [$literal]);
        assert($value instanceof AExprForm);

        return $value;
    }
    /**
     * Builds a binary expression form, parenthesizing operands that bind more weakly than their positions.
     *
     * @param list<string> $symbols
     * @param list<string> $spelling The operator spelling when the operator terminal has several
     */
    protected function infix(array $symbols, Element $left, array $spelling, Element $right): AExprForm
    {
        $value = $this->form('a_expr', $symbols, [$this->operand($left, 'a_expr', $symbols, 0), ...$spelling, $this->operand($right, 'a_expr', $symbols, 2)]);
        assert($value instanceof AExprForm);

        return $value;
    }
    /**
     * Builds a reference of a name followed by `.name` indirection for every further part.
     *
     * @param list<string> $parts
     *
     * @throws CompositionException When there is no name
     */
    protected function qualified(string $rule, array $parts, string $what): Element
    {
        if ($parts === []) {
            throw new CompositionException($what . ' needs at least one name.');
        }
        $first = $this->identifier(array_shift($parts));
        if ($parts === []) {
            return $first;
        }
        $elements = array_map(fn (string $part): Element => $this->form('indirection_el', ['.', 'attr_name'], [$this->name('attr_name', $part)]), $parts);

        return $this->form($rule, ['ColId', 'indirection'], [$first, $this->fold('indirection', ['indirection', 'indirection_el'], $elements, 0, 1)]);
    }
}
