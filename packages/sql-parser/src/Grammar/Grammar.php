<?php

declare(strict_types=1);

namespace SqlParser\Grammar;

/**
 * A context-free grammar with the precedence declarations that disambiguate it.
 *
 * The grammar is augmented: rule zero derives the start symbol followed by
 * the end marker. Token classes, fallbacks and the wildcard are the lexical
 * hints a Lemon grammar carries; a Bison grammar declares none of them.
 *
 * @visibility root
 */
final class Grammar
{
    /**
     * @var array<int, list<int>>
     */
    private readonly array $rulesByLhs;

    /**
     * @param SymbolTable $symbols Every symbol, numbered
     * @param list<Rule> $rules Every rule, the augmented start rule first
     * @param array<int, Precedence> $precedences Declared precedence by terminal
     * @param PrecedencePolicy $policy Which terminal lends an unranked rule its precedence
     * @param int|null $expectedConflicts Shift/reduce conflicts the grammar declares as expected
     * @param array<int, list<int>> $tokenClasses Member terminals by token-class terminal
     * @param array<int, int> $fallbacks Terminal to retry with by the terminal that failed
     * @param int|null $wildcard Terminal that stands for any other, if any
     *
     * @throws GrammarException When the rules are not numbered in order
     */
    public function __construct(
        public readonly SymbolTable $symbols,
        public readonly array $rules,
        public readonly array $precedences = [],
        public readonly PrecedencePolicy $policy = PrecedencePolicy::LastTerminal,
        public readonly ?int $expectedConflicts = null,
        public readonly array $tokenClasses = [],
        public readonly array $fallbacks = [],
        public readonly ?int $wildcard = null,
    ) {
        $rulesByLhs = [];
        foreach ($rules as $index => $rule) {
            if ($rule->index !== $index) {
                throw new GrammarException("Rule {$rule->index} is stored at position {$index}");
            }
            $rulesByLhs[$rule->lhs][] = $index;
        }
        $this->rulesByLhs = $rulesByLhs;
    }

    /**
     * Answers the rules that derive one nonterminal.
     *
     * @param int $nonterminal Nonterminal number
     *
     * @return list<int> Rule indexes in declaration order
     */
    public function rulesOf(int $nonterminal): array
    {
        return $this->rulesByLhs[$nonterminal] ?? [];
    }

    /**
     * Answers the precedence declared for a terminal, if any.
     *
     * @param int $terminal Terminal number
     *
     * @return Precedence|null The declaration, or null when the terminal is unranked
     */
    public function precedenceOf(int $terminal): ?Precedence
    {
        return $this->precedences[$terminal] ?? null;
    }

    /**
     * Answers the precedence a rule carries into conflict resolution.
     *
     * A rule that names a terminal takes that terminal's declaration. Otherwise
     * the policy picks a terminal from the right-hand side, and a token class in
     * that position lends the declaration of its first ranked member.
     *
     * @param Rule $rule Rule to rank
     *
     * @return Precedence|null The declaration, or null when nothing ranks the rule
     */
    public function rulePrecedence(Rule $rule): ?Precedence
    {
        if ($rule->precedenceSymbol !== null) {
            return $this->precedenceOf($rule->precedenceSymbol);
        }
        $chosen = null;
        foreach ($rule->rhs as $symbol) {
            if (!$this->symbols->isTerminal($symbol)) {
                continue;
            }
            $precedence = $this->memberPrecedence($symbol);
            if ($this->policy === PrecedencePolicy::LastTerminal) {
                $chosen = $precedence;
            } elseif ($precedence !== null) {
                return $precedence;
            }
        }

        return $chosen;
    }

    /**
     * Answers the precedence of a terminal, looking through a token class.
     *
     * @param int $terminal Terminal or token-class number
     *
     * @return Precedence|null The first declaration found, or null when none is ranked
     */
    public function memberPrecedence(int $terminal): ?Precedence
    {
        foreach ($this->tokenClasses[$terminal] ?? [$terminal] as $member) {
            $precedence = $this->precedenceOf($member);
            if ($precedence !== null) {
                return $precedence;
            }
        }

        return null;
    }

    /**
     * Answers the nonterminal the augmented start rule derives from.
     *
     * @return int Number of the grammar's own start symbol
     */
    public function startSymbol(): int
    {
        return $this->rules[0]->rhs[0];
    }
}
