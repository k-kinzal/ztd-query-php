<?php

declare(strict_types=1);

namespace LemonParser\Ast;

/**
 * A rule: a nonterminal, `::=`, the right-hand side, and a period.
 *
 * A precedence mark `[TOKEN]` and a code block `{ ... }` may follow the
 * period, and Lemon attaches them to the rule most recently completed.
 * `{NEVER-REDUCE}` in place of code marks a rule that is never reduced.
 *
 * @visibility public
 *
 * @example Reading a rule with everything
 *     $file = (new \LemonParser\Parser())->parse("expr(A) ::= expr(B) MINUS expr(C). [PLUS] { A = B - C; }\n");
 *     $rule = $file->rules()[0];
 *     [$rule->lhs->name, $rule->lhsAlias, count($rule->items), $rule->precedence?->name, $rule->code?->code, $rule->neverReduce] // => ['expr', 'A', 3, 'PLUS', ' A = B - C; ', false]
 */
final class Rule
{
    /**
     * @param Symbol $lhs The nonterminal being defined
     * @param string|null $lhsAlias The name given to the rule's value, or null
     * @param list<RhsItem> $items The right-hand side, possibly empty
     * @param Symbol|null $precedence The terminal of the `[TOKEN]` mark, or null
     * @param CodeBlock|null $code The action, or null
     * @param bool $neverReduce Whether `{NEVER-REDUCE}` follows the rule
     * @param Location $location Where the left-hand side is written
     */
    public function __construct(
        public readonly Symbol $lhs,
        public readonly ?string $lhsAlias,
        public readonly array $items,
        public readonly ?Symbol $precedence,
        public readonly ?CodeBlock $code,
        public readonly bool $neverReduce,
        public readonly Location $location,
    ) {
    }

    /**
     * Copies the rule with a precedence mark.
     *
     * @param Symbol $precedence The terminal of the mark
     *
     * @return self The rule with the mark
     */
    public function withPrecedence(Symbol $precedence): self
    {
        return new self($this->lhs, $this->lhsAlias, $this->items, $precedence, $this->code, $this->neverReduce, $this->location);
    }

    /**
     * Copies the rule with an action.
     *
     * @param CodeBlock $code The action
     *
     * @return self The rule with the action
     */
    public function withCode(CodeBlock $code): self
    {
        return new self($this->lhs, $this->lhsAlias, $this->items, $this->precedence, $code, $this->neverReduce, $this->location);
    }

    /**
     * Copies the rule marked as never reduced.
     *
     * @return self The marked rule
     */
    public function withNeverReduce(): self
    {
        return new self($this->lhs, $this->lhsAlias, $this->items, $this->precedence, $this->code, true, $this->location);
    }

    /**
     * Lists the symbols of the right-hand side in order, every terminal of a shared position included.
     *
     * @return list<Symbol> The symbols
     */
    public function symbols(): array
    {
        $symbols = [];
        foreach ($this->items as $item) {
            foreach ($item->symbols as $symbol) {
                $symbols[] = $symbol;
            }
        }

        return $symbols;
    }
}
