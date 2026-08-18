<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminalInventory;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\PostgreSql\Grammar\PgGrammar;
use SqlFaker\PostgreSqlProvider;

/**
 * Derives PostgreSQL parser terminals and realizes them through the versioned lexer model.
 */
final class SqlGenerator
{
    private const DERIVATION_LIMIT = 5000;
    private const LEXICAL_ATTEMPT_LIMIT = 32;

    private Grammar $grammar;
    private FakerGenerator $faker;
    private LexicalGrammar $lexicalGrammar;
    private TerminationAnalyzer $terminationAnalyzer;
    private int $targetDepth = PHP_INT_MAX;
    private int $derivationSteps = 0;

    public function __construct(
        Grammar $grammar,
        FakerGenerator $faker,
        PostgreSqlProvider $provider,
        ?string $version = null,
    ) {
        unset($provider);
        $this->grammar = $grammar;
        $this->faker = $faker;
        $this->lexicalGrammar = new LexicalGrammar(
            $faker,
            PgGrammar::resolveVersion($version),
            $version === null,
        );
        if ($version !== null) {
            $this->lexicalGrammar->assertTerminalsCovered(TerminalInventory::fromGrammar($this->grammar));
        }
        $this->terminationAnalyzer = new TerminationAnalyzer($grammar, $this->lexicalGrammar->supports(...));
    }

    /**
     * @template TRequiresNonEmpty of bool
     * @param GenerationPlan<TRequiresNonEmpty> $plan
     * @return (TRequiresNonEmpty is true ? non-empty-string : string)
     */
    public function generate(GenerationPlan $plan): string
    {
        $this->targetDepth = $plan->maxDepth();
        $lastException = null;
        for ($attempt = 0; $attempt < self::LEXICAL_ATTEMPT_LIMIT; $attempt++) {
            $this->derivationSteps = 0;
            $terminals = $this->derive($plan->startRule() ?? 'stmtmulti', $plan);
            $terminalNames = $this->normalizeParserSemantics(array_map(
                static fn (Terminal $terminal): string => $terminal->value,
                $terminals,
            ));
            try {
                $sql = $this->lexicalGrammar->realize($terminalNames, $plan);
                if ($sql !== '' || !$plan->requiresNonEmpty()) {
                    return $sql;
                }
                $lastException = new LogicException('PostgreSQL generation plan requires non-empty output.');
            } catch (LexicalException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? new LogicException('PostgreSQL lexical realization failed.');
    }

    /**
     * Applies constraints enforced by parser semantic actions rather than the lexer or Bison grammar.
     *
     * @param list<string> $terminals
     * @return list<string>
     */
    private function normalizeParserSemantics(array $terminals): array
    {
        $terminals = $this->truncateQualifiedNames($terminals);

        foreach ($terminals as $index => $terminal) {
            if ($terminal !== 'SET' || ($terminals[$index + 1] ?? null) !== '(') {
                continue;
            }
            $end = $this->matchingParen($terminals, $index + 1);
            if ($end === null) {
                continue;
            }
            $depth = 1;
            for ($cursor = $index + 2; $cursor < $end; $cursor++) {
                if ($terminals[$cursor] === '(') {
                    $depth++;
                } elseif ($terminals[$cursor] === ')') {
                    $depth--;
                }
                if ($depth === 1
                    && $this->isIdentifierTerminal($terminals[$cursor])
                    && in_array($terminals[$cursor + 1] ?? null, [',', ')'], true)
                    && ($terminals[$cursor - 1] ?? null) !== '='
                ) {
                    array_splice($terminals, $cursor + 1, 0, ['=', 'NONE']);
                    $end += 2;
                    $cursor += 2;
                }
            }
        }

        foreach ($terminals as $index => $terminal) {
            if ($terminal !== 'OPERATOR' || ($terminals[$index + 1] ?? null) === '(') {
                continue;
            }
            $start = array_search('(', array_slice($terminals, $index + 1), true);
            if ($start === false) {
                continue;
            }
            $start += $index + 1;
            $end = $this->matchingParen($terminals, $start);
            if ($end !== null && $end - $start === 2 && $terminals[$start + 1] !== ',') {
                array_splice($terminals, $start + 1, 0, ['NONE', ',']);
            }
        }

        return $this->lexicalGrammar->normalizeLookahead($terminals);
    }

    /**
     * @param list<string> $terminals
     * @return list<string>
     */
    private function truncateQualifiedNames(array $terminals): array
    {
        $result = [];
        $count = count($terminals);
        for ($index = 0; $index < $count; $index++) {
            $terminal = $terminals[$index];
            if (!$this->isIdentifierTerminal($terminal) || ($terminals[$index + 1] ?? null) !== '.') {
                $result[] = $terminal;
                continue;
            }

            $chain = [$terminal];
            while ($index + 2 < $count && $terminals[$index + 1] === '.') {
                $following = $terminals[$index + 2];
                if ($following === '*') {
                    $index += 2;
                    break;
                }
                if (!$this->isIdentifierTerminal($following)) {
                    break;
                }
                $chain[] = '.';
                $chain[] = $following;
                $index += 2;
            }
            array_push($result, ...array_slice($chain, 0, 5));
        }

        return $result;
    }

    /**
     * @param list<string> $terminals
     */
    private function matchingParen(array $terminals, int $open): ?int
    {
        $depth = 0;
        for ($index = $open; $index < count($terminals); $index++) {
            if ($terminals[$index] === '(') {
                $depth++;
            } elseif ($terminals[$index] === ')' && --$depth === 0) {
                return $index;
            }
        }

        return null;
    }

    private function isIdentifierTerminal(string $terminal): bool
    {
        return in_array($terminal, ['IDENT', 'UIDENT'], true)
            || preg_match('/^[a-z_][a-z0-9_]*$/', $terminal) === 1;
    }

    /**
     * @param GenerationPlan<bool> $plan
     * @return list<Terminal>
     */
    private function derive(string $startSymbol, GenerationPlan $plan): array
    {
        /** @var list<Symbol> $form */
        $form = [new NonTerminal($startSymbol)];
        /** @var array<string, int> $occurrences */
        $occurrences = [];

        while (true) {
            $index = $this->firstNonTerminal($form);
            if ($index === null) {
                break;
            }

            $this->derivationSteps++;
            if ($this->derivationSteps > self::DERIVATION_LIMIT) {
                throw new LogicException('Exceeded derivation limit while generating SQL.');
            }

            /** @var NonTerminal $nonTerminal */
            $nonTerminal = $form[$index];
            $rule = $this->grammar->ruleMap[$nonTerminal->value]
                ?? throw new LogicException("Unknown grammar rule: {$nonTerminal->value}");
            if ($rule->alternatives === []) {
                throw new LogicException('Production rule has no alternatives.');
            }
            $alternatives = array_values(array_filter(
                $rule->alternatives,
                $this->terminationAnalyzer->isProductionViable(...),
            ));
            if ($alternatives === []) {
                throw new LogicException("Grammar rule has no lexically realizable alternative: {$nonTerminal->value}");
            }
            $occurrence = $occurrences[$nonTerminal->value] ?? 0;
            $occurrences[$nonTerminal->value] = $occurrence + 1;
            $pattern = $plan->patternAt($nonTerminal->value, $occurrence);
            if ($pattern !== null) {
                $alternatives = array_values(array_filter(
                    $alternatives,
                    static fn (Production $production): bool => $pattern->matches(array_map(
                        static fn (Symbol $symbol): string => $symbol->value(),
                        $production->symbols,
                    )),
                ));
                if ($alternatives === []) {
                    throw new LogicException(
                        "Grammar rule has no alternative matching the generation plan: {$nonTerminal->value}",
                    );
                }
            }
            if ($this->derivationSteps === 1 && $plan->requiresNonEmpty()) {
                $alternatives = array_values(array_filter(
                    $alternatives,
                    fn (Production $production): bool => $this->terminationAnalyzer
                        ->estimateProductionLength($production) > 0,
                ));
                if ($alternatives === []) {
                    throw new LogicException(
                        "Generation plan requires non-empty output, but the start rule cannot produce it: {$nonTerminal->value}",
                    );
                }
            }

            $production = $this->selectProduction($alternatives);
            $form = [
                ...array_slice($form, 0, $index),
                ...$production->symbols,
                ...array_slice($form, $index + 1),
            ];
        }

        /** @var list<Terminal> $form */
        return $form;
    }

    /**
     * @param list<Symbol> $form
     */
    private function firstNonTerminal(array $form): ?int
    {
        foreach ($form as $index => $symbol) {
            if ($symbol instanceof NonTerminal) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param non-empty-array<int, Production> $alternatives
     */
    private function selectProduction(array $alternatives): Production
    {
        if ($this->derivationSteps < $this->targetDepth) {
            $keys = array_keys($alternatives);

            return $alternatives[$keys[$this->faker->numberBetween(0, count($keys) - 1)]];
        }

        $selected = $alternatives[array_key_first($alternatives)];
        $bestLength = $this->terminationAnalyzer->estimateProductionLength($selected);
        foreach (array_slice($alternatives, 1) as $alternative) {
            $length = $this->terminationAnalyzer->estimateProductionLength($alternative);
            if ($length < $bestLength) {
                $selected = $alternative;
                $bestLength = $length;
            }
        }

        return $selected;
    }
}
