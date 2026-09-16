<?php

declare(strict_types=1);

namespace SqlParser\Grammar;

/**
 * Collects the declarations of a grammar source and numbers them into a grammar.
 *
 * Readers meet symbols in whatever order the source spells them, so names are
 * gathered first and numbered only when the grammar is built: terminals in
 * declaration order after the end marker, then the accept symbol and the
 * nonterminals in the order their rules appear.
 *
 * @visibility root
 */
final class GrammarBuilder
{
    /**
     * The name of the nonterminal the augmented start rule derives.
     */
    public const ACCEPT = '$accept';

    /**
     * @var array<string, true>
     */
    private array $terminals = [];

    /**
     * @var array<string, true>
     */
    private array $nonterminals = [];

    /**
     * @var list<array{string, list<string>, string|null, bool}>
     */
    private array $rules = [];

    /**
     * @var array<string, Precedence>
     */
    private array $precedences = [];

    private int $level = 0;

    private ?string $start = null;

    private ?int $expectedConflicts = null;

    private PrecedencePolicy $policy = PrecedencePolicy::LastTerminal;

    /**
     * @var array<string, list<string>>
     */
    private array $tokenClasses = [];

    /**
     * @var array<string, string>
     */
    private array $fallbacks = [];

    private ?string $wildcard = null;

    /**
     * Declares a terminal, keeping the position of its first declaration.
     *
     * @param string $name Terminal name
     */
    public function terminal(string $name): void
    {
        $this->terminals[$name] = true;
    }

    /**
     * Declares a nonterminal, keeping the position of its first declaration.
     *
     * @param string $name Nonterminal name
     */
    public function nonterminal(string $name): void
    {
        $this->nonterminals[$name] = true;
    }

    /**
     * Reports whether a name has been declared as a terminal.
     *
     * @param string $name Symbol name
     *
     * @return bool True when the name is a terminal or a token class
     */
    public function isTerminal(string $name): bool
    {
        return isset($this->terminals[$name]) || isset($this->tokenClasses[$name]);
    }

    /**
     * Reports whether a name has been declared as a nonterminal.
     *
     * @param string $name Symbol name
     *
     * @return bool True when a rule derives the name
     */
    public function isNonterminal(string $name): bool
    {
        return isset($this->nonterminals[$name]);
    }

    /**
     * Records one production.
     *
     * The left-hand side becomes a nonterminal; right-hand names are resolved
     * when the grammar is built, so they may be declared later.
     *
     * @param string $lhs Nonterminal the rule derives
     * @param list<string> $rhs Symbol names the rule expands to
     * @param string|null $precedenceSymbol Terminal named to lend its precedence
     * @param bool $hidden Whether the rule stands in for a mid-rule action
     */
    public function rule(string $lhs, array $rhs, ?string $precedenceSymbol = null, bool $hidden = false): void
    {
        $this->nonterminal($lhs);
        $this->rules[] = [$lhs, $rhs, $precedenceSymbol, $hidden];
    }

    /**
     * Records one precedence declaration, ranking above every earlier one.
     *
     * @param list<string> $names Terminals the declaration ranks
     * @param Associativity $associativity How ties within the rank are settled
     */
    public function precedence(array $names, Associativity $associativity): void
    {
        $this->level++;
        foreach ($names as $name) {
            $this->terminal($name);
            $this->precedences[$name] = new Precedence($this->level, $associativity);
        }
    }

    /**
     * Names the start symbol; the first rule's left-hand side is used otherwise.
     *
     * @param string $name Nonterminal to start from
     */
    public function start(string $name): void
    {
        $this->start = $name;
    }

    /**
     * Records how many shift/reduce conflicts the source declares as expected.
     *
     * @param int $count Declared conflict count
     */
    public function expect(int $count): void
    {
        $this->expectedConflicts = $count;
    }

    /**
     * Chooses how an unranked rule borrows its precedence.
     *
     * @param PrecedencePolicy $policy The generator's convention
     */
    public function policy(PrecedencePolicy $policy): void
    {
        $this->policy = $policy;
    }

    /**
     * Declares a token class, a terminal that stands for any of its members.
     *
     * @param string $name Class name
     * @param list<string> $members Terminals the class stands for
     */
    public function tokenClass(string $name, array $members): void
    {
        foreach ($members as $member) {
            $this->terminal($member);
        }
        $this->tokenClasses[$name] = $members;
    }

    /**
     * Declares which terminal to retry with when a listed terminal cannot be used.
     *
     * @param string $target Terminal to fall back to
     * @param list<string> $names Terminals that fall back
     */
    public function fallback(string $target, array $names): void
    {
        $this->terminal($target);
        foreach ($names as $name) {
            $this->terminal($name);
            $this->fallbacks[$name] = $target;
        }
    }

    /**
     * Declares the terminal that stands for any other.
     *
     * @param string $name Wildcard terminal name
     */
    public function wildcard(string $name): void
    {
        $this->terminal($name);
        $this->wildcard = $name;
    }

    /**
     * Numbers everything collected and answers the augmented grammar.
     *
     * @return Grammar The grammar
     *
     * @throws GrammarException When no rule was recorded or a name is both a terminal and a nonterminal
     * @throws UnknownSymbolException When a rule names a symbol nothing declares
     */
    public function build(): Grammar
    {
        if ($this->rules === []) {
            throw new GrammarException('A grammar needs at least one rule');
        }
        foreach (array_keys($this->nonterminals) as $name) {
            if ($this->isTerminal($name)) {
                throw new GrammarException("Symbol '{$name}' is both a terminal and a nonterminal");
            }
        }
        $symbols = new SymbolTable(
            [SymbolTable::END, ...array_keys($this->terminals), ...array_keys($this->tokenClasses)],
            [self::ACCEPT, ...array_keys($this->nonterminals)],
        );
        $start = $this->start ?? $this->firstVisibleLhs();
        $rules = [new Rule(0, $this->id($symbols, self::ACCEPT), [$this->id($symbols, $start), 0], 0)];
        foreach ($this->numberedRules($symbols) as $rule) {
            $rules[] = $rule;
        }
        $precedences = [];
        foreach ($this->precedences as $name => $precedence) {
            $precedences[$this->id($symbols, $name)] = $precedence;
        }
        $tokenClasses = [];
        foreach ($this->tokenClasses as $name => $members) {
            $tokenClasses[$this->id($symbols, $name)] = array_map(fn (string $member): int => $this->id($symbols, $member), $members);
        }
        $fallbacks = [];
        foreach ($this->fallbacks as $name => $target) {
            $fallbacks[$this->id($symbols, $name)] = $this->id($symbols, $target);
        }

        return new Grammar(
            $symbols,
            $rules,
            $precedences,
            $this->policy,
            $this->expectedConflicts,
            $tokenClasses,
            $fallbacks,
            $this->wildcard === null ? null : $this->id($symbols, $this->wildcard),
        );
    }

    /**
     * Numbers the recorded rules after the augmented start rule.
     *
     * @param SymbolTable $symbols The numbering
     *
     * @return list<Rule> Rules numbered from one, alternatives counted per nonterminal
     *
     * @throws UnknownSymbolException When a rule names a symbol nothing declares
     */
    public function numberedRules(SymbolTable $symbols): array
    {
        $rules = [];
        $ordinals = [];
        foreach ($this->rules as [$lhs, $rhs, $precedenceSymbol, $hidden]) {
            $lhsId = $this->id($symbols, $lhs);
            $ordinal = $hidden ? 0 : ($ordinals[$lhsId] ?? 0);
            if (!$hidden) {
                $ordinals[$lhsId] = $ordinal + 1;
            }
            $rules[] = new Rule(
                count($rules) + 1,
                $lhsId,
                array_map(fn (string $name): int => $this->id($symbols, $name), $rhs),
                $ordinal,
                $hidden,
                $precedenceSymbol === null ? null : $this->id($symbols, $precedenceSymbol),
            );
        }

        return $rules;
    }

    /**
     * Answers the left-hand side of the first rule that was not synthesised.
     *
     * A rule synthesised for a mid-rule action is numbered before the rule
     * it appears in, so the first recorded rule is not always the first rule
     * the source wrote.
     *
     * @return string Nonterminal of the first rule as written
     */
    public function firstVisibleLhs(): string
    {
        foreach ($this->rules as [$lhs, $rhs, $precedenceSymbol, $hidden]) {
            if (!$hidden) {
                return $lhs;
            }
        }

        return $this->rules[0][0];
    }

    /**
     * Resolves a name against the numbered symbols.
     *
     * @param SymbolTable $symbols The numbering
     * @param string $name Symbol name
     *
     * @return int The symbol number
     *
     * @throws UnknownSymbolException When nothing declares the name
     */
    public function id(SymbolTable $symbols, string $name): int
    {
        $id = $symbols->id($name);
        if ($id === null) {
            throw new UnknownSymbolException($name);
        }

        return $id;
    }
}
