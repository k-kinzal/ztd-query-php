<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Composition;

use SqlSemantics\Statement\Element;

/**
 * Decides whether an operand may stand unparenthesized at a position of an expression form.
 *
 * The generated contracts of a database package record the binding power of
 * every expression form, the least power each operand position of a form
 * accepts, by grammar release, and the grammar rule of each form. A builder
 * gives them here to parenthesize only the operands the grammar would
 * otherwise bind differently. Precedence orders the forms of one rule, so an
 * operand of another rule, reached through the grammar's own reductions, is
 * not compared.
 *
 * @visibility SqlSemantics
 */
final class Operands
{
    /**
     * @param array<class-string<Element>, array<string, int>> $powers Binding power of each form by release
     * @param array<class-string<Element>, array<string, array<int, int>>> $minimums Least operand power by form, release, and symbol position
     * @param array<class-string<Element>, string> $rules The grammar rule of each expression form
     * @param string $version The release the forms are built for
     */
    public function __construct(private readonly array $powers, private readonly array $minimums, private readonly array $rules, private readonly string $version)
    {
    }

    /**
     * Answers whether the operand binds strongly enough for a symbol position of a form.
     *
     * A value that is not an expression form of the contracts, such as a
     * literal or a parenthesized expression, binds as strongly as anything.
     *
     * @param class-string<Element> $form The form the operand is placed in
     * @param int $position The symbol position of the operand in the form
     * @param string $rule The grammar rule of the position
     */
    public function fits(Element $operand, string $form, int $position, string $rule): bool
    {
        $minimum = $this->minimums[$form][$this->version][$position] ?? null;
        if ($minimum === null || ($this->rules[$operand::class] ?? $rule) !== $rule) {
            return true;
        }

        return ($this->powers[$operand::class][$this->version] ?? PHP_INT_MAX) >= $minimum;
    }
}
