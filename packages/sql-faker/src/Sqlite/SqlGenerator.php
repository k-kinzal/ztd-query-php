<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminalInventory;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\Sqlite\Grammar\SqliteGrammar;
use SqlFaker\SqliteProvider;

/**
 * Grammar-driven SQL generator for SQLite.
 *
 * Generates syntactically valid SQL strings using SQLite's official Lemon grammar.
 * It implements formal grammar derivation: starting from a non-terminal symbol,
 * repeatedly replacing non-terminals with production rule right-hand sides
 * until only terminal symbols remain.
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
        SqliteProvider $provider,
        ?string $version = null,
    ) {
        unset($provider);
        $this->grammar = $this->augmentGrammar($grammar);
        $this->faker = $faker;
        $this->lexicalGrammar = new LexicalGrammar(
            $faker,
            SqliteGrammar::resolveVersion($version),
            $version === null,
        );
        if ($version !== null) {
            $this->lexicalGrammar->assertTerminalsCovered(TerminalInventory::fromGrammar($this->grammar));
        }
        $this->terminationAnalyzer = new TerminationAnalyzer(
            $this->grammar,
            $this->lexicalGrammar->supports(...),
        );
    }

    /**
     * Generate a syntactically valid SQL string.
     *
     * @param string|null $startRule Grammar rule to start from (null for default)
     * @param int $targetDepth Depth at which generator starts seeking termination
     */
    public function generate(?string $startRule = null, int $targetDepth = PHP_INT_MAX): string
    {
        $this->targetDepth = max(1, $targetDepth);
        $start = $startRule ?? 'cmd';
        $lastException = null;
        for ($attempt = 0; $attempt < self::LEXICAL_ATTEMPT_LIMIT; $attempt++) {
            $this->derivationSteps = 0;
            $terminals = $this->derive($start);
            try {
                return $this->lexicalGrammar->realize(array_map(
                    static fn (Terminal $terminal): string => $terminal->value,
                    $terminals,
                ));
            } catch (LexicalException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? new LogicException('SQLite lexical realization failed.');
    }

    /**
     * @return list<Terminal>
     */
    private function derive(string $startSymbol): array
    {
        /** @var list<Symbol> $form */
        $form = [new NonTerminal($startSymbol)];

        while (true) {
            $index = null;
            foreach ($form as $i => $sym) {
                if ($sym instanceof NonTerminal) {
                    $index = $i;
                    break;
                }
            }

            if ($index === null) {
                break;
            }

            $this->derivationSteps++;
            if ($this->derivationSteps > self::DERIVATION_LIMIT) {
                throw new LogicException('Exceeded derivation limit while generating SQL.');
            }

            /** @var NonTerminal $nonTerminal */
            $nonTerminal = $form[$index];

            if (!isset($this->grammar->ruleMap[$nonTerminal->value])) {
                $form[$index] = new Terminal($nonTerminal->value);
                continue;
            }

            $rule = $this->grammar->ruleMap[$nonTerminal->value];
            if ($rule->alternatives === []) {
                throw new LogicException("Production rule '{$nonTerminal->value}' has no alternatives.");
            }
            $alternatives = array_values(array_filter(
                $rule->alternatives,
                $this->terminationAnalyzer->isProductionViable(...),
            ));

            if ($alternatives === []) {
                throw new LogicException("Grammar rule has no lexically realizable alternative: {$nonTerminal->value}");
            }

            if ($this->derivationSteps >= $this->targetDepth) {
                $selectedIndex = 0;
                $bestLength = PHP_INT_MAX;
                foreach ($alternatives as $i => $alt) {
                    $length = $this->terminationAnalyzer->estimateProductionLength($alt);
                    if ($length < $bestLength) {
                        $bestLength = $length;
                        $selectedIndex = $i;
                    }
                }
            } else {
                $selectedIndex = $this->faker->numberBetween(0, count($alternatives) - 1);
            }

            $production = $alternatives[$selectedIndex];

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
     * Augment the grammar with synthetic wrapper rules for statement types
     * that don't have their own top-level rules in SQLite's grammar.
     *
     * In SQLite's parse.y, DELETE/UPDATE/INSERT/ALTER TABLE/DROP TABLE are embedded
     * directly as cmd alternatives. We extract these into dedicated rules.
     */
    private function augmentGrammar(Grammar $grammar): Grammar
    {
        $ruleMap = $grammar->ruleMap;
        $cmd = $ruleMap['cmd'] ?? null;

        if ($cmd === null) {
            return $grammar;
        }

        $ruleMap = $this->filterExprRule($ruleMap);
        $ruleMap = $this->filterWindowRule($ruleMap);
        $ruleMap = $this->extractStatementRules($ruleMap, $cmd);

        return new Grammar($grammar->startSymbol, $ruleMap);
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function extractStatementRules(array $ruleMap, ProductionRule $cmd): array
    {
        $groups = $this->classifyCmdAlternatives($cmd);

        $groups['delete'] = array_values(array_filter(
            $groups['delete'],
            fn (Production $alt): bool => !$this->hasNonTerminal($alt, 'orderby_opt'),
        ));

        $ruleNames = [
            'insert' => 'insert',
            'delete' => 'delete',
            'update' => 'update',
            'drop_table' => 'drop_table',
            'alter_table' => 'alter_table',
        ];

        foreach ($ruleNames as $key => $ruleName) {
            if ($groups[$key] !== []) {
                $ruleMap[$ruleName] = new ProductionRule($ruleName, $groups[$key]);
            }
        }

        return $ruleMap;
    }

    /**
     * @return array<string, list<Production>>
     */
    private function classifyCmdAlternatives(ProductionRule $cmd): array
    {
        $groups = [
            'insert' => [],
            'delete' => [],
            'update' => [],
            'drop_table' => [],
            'alter_table' => [],
        ];

        foreach ($cmd->alternatives as $alt) {
            if ($alt->symbols === []) {
                continue;
            }

            $first = $alt->symbols[0];

            if ($first instanceof Terminal) {
                match ($first->value) {
                    'DELETE' => $groups['delete'][] = $alt,
                    'UPDATE' => $groups['update'][] = $alt,
                    'ALTER' => $this->secondTerminalIs($alt, 'TABLE') ? $groups['alter_table'][] = $alt : null,
                    'DROP' => $this->secondTerminalIs($alt, 'TABLE') ? $groups['drop_table'][] = $alt : null,
                    default => null,
                };
            } elseif ($first instanceof NonTerminal && count($alt->symbols) >= 2) {
                $second = $alt->symbols[1];
                if ($second instanceof Terminal) {
                    match ($second->value) {
                        'DELETE' => $groups['delete'][] = $alt,
                        'UPDATE' => $groups['update'][] = $alt,
                        default => null,
                    };
                } elseif ($second instanceof NonTerminal && $second->value === 'insert_cmd') {
                    $groups['insert'][] = $alt;
                }
            }
        }

        return $groups;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterExprRule(array $ruleMap): array
    {
        $expr = $ruleMap['expr'] ?? null;
        if ($expr === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $expr->alternatives,
            fn (Production $alt): bool => !$this->hasTerminalValue($alt, 'WITHIN'),
        ));
        if ($filtered !== []) {
            $ruleMap['expr'] = new ProductionRule('expr', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterWindowRule(array $ruleMap): array
    {
        $window = $ruleMap['window'] ?? null;
        if ($window === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $window->alternatives,
            static fn (Production $alt): bool => !self::isFrameOptOnlyAlternative($alt),
        ));
        if ($filtered !== []) {
            $ruleMap['window'] = new ProductionRule('window', $filtered);
        }

        return $ruleMap;
    }

    private static function isFrameOptOnlyAlternative(Production $alt): bool
    {
        $nonTerminals = [];
        $hasTerminal = false;
        foreach ($alt->symbols as $sym) {
            if ($sym instanceof NonTerminal) {
                $nonTerminals[] = $sym->value;
            } elseif ($sym instanceof Terminal) {
                $hasTerminal = true;
            }
        }

        return !$hasTerminal && in_array('frame_opt', $nonTerminals, true)
            && array_diff($nonTerminals, ['nm', 'frame_opt']) === [];
    }

    private function hasTerminalValue(Production $alt, string $value): bool
    {
        foreach ($alt->symbols as $sym) {
            if ($sym instanceof Terminal && $sym->value === $value) {
                return true;
            }
        }
        return false;
    }

    private function secondTerminalIs(Production $alt, string $value): bool
    {
        $s1 = $alt->symbols[1] ?? null;

        return $s1 instanceof Terminal && $s1->value === $value;
    }

    private function hasNonTerminal(Production $alt, string $name): bool
    {
        foreach ($alt->symbols as $sym) {
            if ($sym instanceof NonTerminal && $sym->value === $name) {
                return true;
            }
        }
        return false;
    }

}
