<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use function assert;

use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\Sqlite\Role\ExprForm;

/**
 * Spells the names, terms, and operators of SQLite expressions, and the column lists of its common table expressions.
 *
 * @visibility SqlSemantics
 */
trait Expressions
{
    /**
     * Reads a term or bare word as an expression.
     */
    protected function term(string $text): ExprForm
    {
        $value = $this->leaf('expr', $text);
        assert($value instanceof ExprForm);

        return $value;
    }    /**
     * Applies the unary minus to a literal when the value is negative.
     */
    protected function signed(bool $negative, ExprForm $literal): ExprForm
    {
        if (!$negative) {
            return $literal;
        }
        $value = $this->form('expr', ['PLUS|MINUS', 'expr'], ['-', $literal]);
        assert($value instanceof ExprForm);

        return $value;
    }
    /**
     * Builds a binary expression form, parenthesizing operands that bind more weakly than their positions.
     *
     * @param list<string> $symbols
     * @param list<string> $spelling The operator spelling when the operator symbol is a terminal class
     */
    protected function infix(array $symbols, Element $left, array $spelling, Element $right): ExprForm
    {
        $value = $this->form('expr', $symbols, [$this->operand($left, 'expr', $symbols, 0), ...$spelling, $this->operand($right, 'expr', $symbols, 2)]);
        assert($value instanceof ExprForm);

        return $value;
    }
    /**
     * Folds column entries into the left-recursive eidlist form.
     *
     * @param list<Element> $entries Values of the `nm collate sortorder` form
     *
     * @throws CompositionException When there is no entry
     */
    protected function eidlist(array $entries): Element
    {
        $list = array_shift($entries) ?? throw new CompositionException('A column list needs at least one name.');
        foreach ($entries as $entry) {
            $list = $this->form('eidlist', ['eidlist', 'COMMA', 'nm', 'collate', 'sortorder'], [$list, ...$entry->children()]);
        }

        return $list;
    }
}
