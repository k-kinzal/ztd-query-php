<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Lemon;

/**
 * Reads the rules a Lemon grammar writes.
 *
 * Lemon writes one alternative per line rather than grouping them, so the
 * alternatives of a rule are gathered by name as the file is read. Within a
 * line it allows an alternation between tokens in a single position, which
 * stands for as many alternatives as the positions multiply out to, and it
 * lets each symbol carry an alias the parser uses to name its value.
 *
 * @visibility root
 */
final class LemonRules
{
    /**
     * @param LemonText $text Removes what is not grammar before the rules are looked for
     */
    public function __construct(private readonly LemonText $text = new LemonText())
    {
    }

    /**
     * Reads every rule, telling the symbol table what each name turned out to be.
     *
     * @param string $input Contents of the grammar file
     * @param LemonSymbols $symbols Symbol table to record names in
     *
     * @return array<string, list<list<string>>> Rule name => its alternatives, each a list of symbol names
     */
    public function readFrom(string $input, LemonSymbols $symbols): array
    {
        /** @var array<string, list<list<string>>> $rules */
        $rules = [];

        $input = $this->text->withoutDirectiveBlocks($input);
        $pattern = '/^(\w+)(?:\([^)]*\))?\s*::=\s*(.*?)\.\s*(?:\{[^}]*\})?/ms';
        if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $lhs = $match[1];
            $rhs = trim($match[2]);
            if (str_starts_with($lhs, '%')) {
                continue;
            }

            $symbols->declareRule($lhs);
            if (!isset($rules[$lhs])) {
                $rules[$lhs] = [];
            }
            if ($rhs === '') {
                $rules[$lhs][] = [];
                continue;
            }
            array_push($rules[$lhs], ...$this->alternatives($rhs, $symbols));
        }

        return $rules;
    }

    /**
     * Multiplies out one right-hand side into the alternatives it stands for.
     *
     * A position written as `A|B` means either, so a right-hand side with two
     * such positions stands for four alternatives. Positions Lemon marks with
     * a leading percent sign configure the parser rather than name a symbol.
     *
     * @param string $rhs Right-hand side as the grammar writes it
     * @param LemonSymbols $symbols Symbol table to record names in
     *
     * @return non-empty-list<list<string>> Every alternative the right-hand side stands for
     */
    public function alternatives(string $rhs, LemonSymbols $symbols): array
    {
        /** @var non-empty-list<list<string>> $alternatives */
        $alternatives = [[]];

        $parts = preg_split('/\s+/', $rhs);
        if ($parts === false) {
            return [[]];
        }

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $names = array_values(array_filter(array_map(
                $this->withoutAlias(...),
                explode('|', $part),
            ), static fn (string $name): bool => $name !== '' && !str_starts_with($name, '%')));
            if ($names === []) {
                continue;
            }

            foreach ($names as $name) {
                if (LemonSymbols::isTokenName($name)) {
                    $symbols->declareToken($name);
                } else {
                    $symbols->declareRule($name);
                }
            }

            $expanded = [];
            foreach ($alternatives as $alternative) {
                foreach ($names as $name) {
                    $expanded[] = [...$alternative, $name];
                }
            }
            $alternatives = $expanded;
        }

        return $alternatives;
    }

    /**
     * Drops the alias Lemon lets a symbol carry, as in `expr(A)`.
     *
     * @param string $symbol Symbol as the grammar writes it
     *
     * @return string The symbol's name alone
     */
    public function withoutAlias(string $symbol): string
    {
        $open = strpos($symbol, '(');

        return $open !== false ? substr($symbol, 0, $open) : $symbol;
    }
}
