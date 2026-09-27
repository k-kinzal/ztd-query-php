<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use InvalidArgumentException;

/**
 * Expresses the construction invariants of immutable SQL values.
 *
 * @visibility SqlSemantics
 */
trait Assertion
{
    /**
     * States that lowering a parser root produced a complete command.
     *
     * @phpstan-assert Command $element
     */
    protected function assertCompleteCommand(Element $element): void
    {
        $this->assert($element instanceof Command, 'A statement root must be a complete SQL command or command sequence.');
    }

    /**
     * States that sharing the value graph cannot expose mutable object state.
     */
    protected function assertImmutableValueGraph(Element $element): void
    {
        $this->assert((new ImmutableGraph())->containsOnlyImmutableValues($element), 'A shared SQL value graph must contain only final objects with readonly scalar or immutable SQL fields.');
    }

    /**
     * States a condition that must hold for the represented SQL structure.
     */
    protected function assert(bool $condition, string $description): void
    {
        assert($condition, $description);
    }

    /**
     * States that the replacement of a child value occupies the role of its position.
     *
     * @template T of Element
     * @param T $value The child value to replace
     * @param class-string<T> $role The role the position accepts
     * @param callable(Element): Element $replace
     * @return T
     *
     * @throws InvalidArgumentException When the replacement is not a value of the role
     */
    protected function replacement(Element $value, string $role, callable $replace): Element
    {
        $replaced = $replace($value);
        if (!$replaced instanceof $role) {
            throw new InvalidArgumentException('A replacement must be a ' . $role . ', ' . $replaced::class . ' given.');
        }

        return $replaced;
    }

    /**
     * States that a lexical field occupies exactly its declared spelling domain.
     */
    protected function assertMatchesPattern(string $value, string $pattern, string $description): void
    {
        $this->assert(preg_match($pattern, $value) === 1, $description);
    }

    /**
     * States that an unparenthesized operand of the same grammar rule preserves its parent's grouping.
     *
     * Precedence orders the forms of one rule; an operand of another rule is
     * reached through the grammar's own chain of reductions, which precedence
     * does not govern, so it is not compared.
     *
     * @param array<class-string<Element>, array<string, int>> $powers
     * @param array<string, int> $minimums Minimum binding strength by grammar release
     * @param array<class-string<Element>, string> $rules The grammar rule of each expression form
     * @param string $rule The grammar rule of the operand position
     */
    protected function assertOperandBindingStrength(Element $operand, array $powers, array $minimums, array $rules = [], string $rule = ''): void
    {
        if ($rules !== [] && ($rules[$operand::class] ?? $rule) !== $rule) {
            return;
        }
        foreach ($minimums as $version => $minimum) {
            $this->assert(($powers[$operand::class][$version] ?? PHP_INT_MAX) >= $minimum, 'An operand must bind strongly enough in this position; use an explicit parenthesized expression.');
        }
    }
}
