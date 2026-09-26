<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

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
     * States that a lexical field occupies exactly its declared spelling domain.
     */
    protected function assertMatchesPattern(string $value, string $pattern, string $description): void
    {
        $this->assert(preg_match($pattern, $value) === 1, $description);
    }

    /**
     * States that an unparenthesized operand preserves its parent's grouping.
     *
     * @param array<class-string<Element>, array<string, int>> $powers
     * @param array<string, int> $minimums Minimum binding strength by grammar release
     */
    protected function assertOperandBindingStrength(Element $operand, array $powers, array $minimums): void
    {
        foreach ($minimums as $version => $minimum) {
            $this->assert(($powers[$operand::class][$version] ?? PHP_INT_MAX) >= $minimum, 'An operand must bind strongly enough in this position; use an explicit parenthesized expression.');
        }
    }
}
