<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\Symbol;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminalInventory;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;
use SqlFaker\MySqlProvider;

/**
 * Derives MySQL parser terminals and realizes them through the versioned lexer model.
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
        MySqlProvider $provider,
        ?string $version = null,
    ) {
        unset($provider);
        $this->grammar = $grammar;
        $this->faker = $faker;
        $this->lexicalGrammar = new LexicalGrammar(
            $faker,
            Grammar::resolveVersion($version),
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
        $startSymbol = $this->resolveStartSymbol($plan->startRule());
        $lastException = null;
        for ($attempt = 0; $attempt < self::LEXICAL_ATTEMPT_LIMIT; $attempt++) {
            $this->derivationSteps = 0;
            $terminals = $this->derive($startSymbol, $plan);
            $terminalNames = $this->normalizeParserSemantics(array_map(
                static fn (Terminal $terminal): string => $terminal->value,
                $terminals,
            ));
            try {
                $sql = $this->lexicalGrammar->realize($terminalNames);
                if ($sql !== '' || !$plan->requiresNonEmpty()) {
                    return $sql;
                }
                $lastException = new LogicException('MySQL generation plan requires non-empty output.');
            } catch (LexicalException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? new LogicException('MySQL lexical realization failed.');
    }

    private function resolveStartSymbol(?string $requested): string
    {
        if ($requested === null) {
            if (isset($this->grammar->ruleMap['simple_statement_or_begin'])) {
                return 'simple_statement_or_begin';
            }

            return isset($this->grammar->ruleMap['statement']) ? 'statement' : $this->grammar->startSymbol;
        }
        if (isset($this->grammar->ruleMap[$requested])) {
            return $requested;
        }

        $fallbacks = [
            'select_stmt' => 'select',
            'insert_stmt' => 'insert',
            'update_stmt' => 'update',
            'delete_stmt' => 'delete',
            'create_table_stmt' => 'create',
            'alter_table_stmt' => 'alter',
            'drop_table_stmt' => 'drop',
            'simple_statement' => 'statement',
            'simple_statement_or_begin' => $this->grammar->startSymbol,
        ];
        $fallback = $fallbacks[$requested] ?? $requested;

        return isset($this->grammar->ruleMap[$fallback]) ? $fallback : $requested;
    }

    /**
     * Applies constraints enforced by parser semantic actions rather than the lexer or Bison grammar.
     *
     * @param list<string> $terminals
     * @return list<string>
     */
    private function normalizeParserSemantics(array $terminals): array
    {
        $remove = [];
        foreach ($terminals as $index => $terminal) {
            if ($terminal !== '@') {
                continue;
            }
            for ($dot = $index - 2; $dot >= 1 && $terminals[$dot] === '.'; $dot -= 2) {
                $remove[$dot] = true;
                $remove[$dot - 1] = true;
            }
        }
        if ($remove !== []) {
            $terminals = array_values(array_diff_key($terminals, $remove));
        }

        foreach ($terminals as $index => $terminal) {
            if (in_array($terminal, ['CURRENT_USER', 'CURRENT_USER_SYM'], true)
                && ($terminals[$index + 1] ?? null) === '('
                && ($terminals[$index + 2] ?? null) === ')'
                && ($terminals[$index + 3] ?? null) === ':'
            ) {
                array_splice($terminals, $index + 1, 2);
            }
        }

        $event = array_search('EVENT_SYM', $terminals, true);
        $alter = array_search('ALTER_SYM', $terminals, true);
        if ($event !== false && $alter !== false) {
            $afterName = $event + 2;
            if (($terminals[$afterName] ?? null) === '.' && isset($terminals[$afterName + 1])) {
                $afterName += 2;
            }
            if ($afterName >= count($terminals)) {
                $terminals[] = 'ENABLE_SYM';
            }
        }

        $result = [];
        foreach ($terminals as $index => $terminal) {
            $previous = $result[count($result) - 1] ?? null;
            if ($terminal === 'EQUAL_SYM'
                && in_array($terminals[$index + 1] ?? null, ['ALL', 'ALL_SYM', 'ANY', 'ANY_SYM', 'SOME', 'SOME_SYM'], true)
            ) {
                $terminal = 'EQ';
            }
            if (in_array($terminal, ['RELEASE', 'RELEASE_SYM'], true)
                && in_array($previous, ['CHAIN', 'CHAIN_SYM'], true)
                && !in_array($result[count($result) - 2] ?? null, ['NO', 'NO_SYM'], true)
            ) {
                continue;
            }
            if (in_array($terminal, ['DECIMAL_NUM', 'FLOAT_NUM'], true)
                && ($previous === ':' || in_array($previous, ['SYSTEM', 'SYSTEM_SYM'], true))
            ) {
                $terminal = 'NUM';
            }
            $result[] = $terminal;
        }

        return $result;
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
                        "Grammar rule has no alternative matching the derivation plan: {$nonTerminal->value}",
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
