<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Symbol;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;

/**
 * Grammar-driven SQL generator for MySQL.
 *
 * This class generates syntactically valid SQL strings using MySQL's official grammar.
 * It implements formal grammar derivation: starting from a non-terminal symbol,
 * repeatedly replacing non-terminals with production rule right-hand sides
 * until only terminal symbols remain.
 */
final class SqlGenerator
{
    private const DERIVATION_LIMIT = 5000;

    private Grammar $grammar;
    private FakerGenerator $faker;
    private TerminationAnalyzer $terminationAnalyzer;

    private int $targetDepth = PHP_INT_MAX;
    private int $derivationSteps = 0;

    public function __construct(Grammar $grammar, FakerGenerator $faker)
    {
        $this->grammar = $grammar;
        $this->faker = $faker;
        $this->terminationAnalyzer = new TerminationAnalyzer($grammar);
    }

    /**
     * Generate a syntactically valid SQL string.
     *
     * @param string|null $startRule Grammar rule to start from (null for default)
     * @param int $targetDepth Depth at which generator starts seeking termination (PHP_INT_MAX = unlimited)
     */
    public function generate(?string $startRule = null, int $targetDepth = PHP_INT_MAX): string
    {
        $this->derivationSteps = 0;
        $this->targetDepth = max(1, $targetDepth);

        $start = $startRule ?? 'simple_statement_or_begin';

        $terminals = $this->derive($start);

        return $this->render($terminals);
    }

    /**
     * Derivation: repeatedly replace non-terminals with production rule right-hand sides
     * until only terminal symbols remain.
     *
     * @return list<Terminal>
     */
    private function derive(string $startSymbol): array
    {
        /** @var list<Symbol> $form */
        $form = [new NonTerminal($startSymbol)];

        while (true) {
            // Find the first non-terminal
            $index = null;
            foreach ($form as $i => $sym) {
                if ($sym instanceof NonTerminal) {
                    $index = $i;
                    break;
                }
            }

            // All terminals - derivation complete
            if ($index === null) {
                break;
            }

            $this->derivationSteps++;
            if ($this->derivationSteps > self::DERIVATION_LIMIT) {
                throw new LogicException('Exceeded derivation limit while generating SQL.');
            }

            // Select a production rule and replace the non-terminal
            /** @var NonTerminal $nonTerminal */
            $nonTerminal = $form[$index];
            $rule = $this->grammar->ruleMap[$nonTerminal->value];
            $alternatives = $rule->alternatives;

            if ($alternatives === []) {
                throw new LogicException('Production rule has no alternatives.');
            }

            if ($this->derivationSteps >= $this->targetDepth) {
                // Select shortest alternative to terminate quickly
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
     * Render terminals into an SQL string.
     *
     * This method handles:
     * 1. Terminal resolution: converts Terminal symbols to their string representation
     * 2. Sanitization: fixes for constructs that exist in the grammar but are
     *    rejected by MySQL via semantic actions (manual parse errors).
     * 3. Spacing: MySQL's lexer distinguishes some function tokens by requiring
     *    the '(' to follow immediately (e.g. COUNT(*)).
     *
     * @param list<Terminal> $terminals
     */
    private function render(array $terminals): string
    {
        // Resolve terminals to tokens
        $tokens = [];
        foreach ($terminals as $terminal) {
            $name = $terminal->value;

            $token = match ($name) {
                // Grammar-only token: never emitted
                'END_OF_INPUT' => null,

                // Operators
                'EQ' => '=',
                'EQUAL_SYM' => '<=>',
                'LT' => '<',
                'GT_SYM' => '>',
                'LE' => '<=',
                'GE' => '>=',
                'NE' => '<>',
                'SHIFT_LEFT' => '<<',
                'SHIFT_RIGHT' => '>>',
                'AND_AND_SYM' => '&&',
                'OR2_SYM', 'OR_OR_SYM' => '||',
                'NOT2_SYM' => 'NOT',
                'SET_VAR' => ':=',
                'JSON_SEPARATOR_SYM' => '->',
                'JSON_UNQUOTED_SEPARATOR_SYM' => '->>',
                'NEG' => '-',
                'PARAM_MARKER' => '?',

                // Lexical tokens (delegated to Faker provider)
                'IDENT' => $this->faker->identifier(),
                'IDENT_QUOTED' => $this->faker->quotedIdentifier(),
                'TEXT_STRING' => $this->faker->stringLiteral(),
                'NCHAR_STRING' => $this->faker->nationalStringLiteral(),
                'DOLLAR_QUOTED_STRING_SYM' => $this->faker->dollarQuotedString(),
                'NUM' => $this->faker->integerLiteral(),
                'LONG_NUM' => $this->faker->longIntegerLiteral(),
                'ULONGLONG_NUM' => $this->faker->unsignedBigIntLiteral(),
                'DECIMAL_NUM' => $this->faker->decimalLiteral(),
                'FLOAT_NUM' => $this->faker->floatLiteral(),
                'HEX_NUM' => $this->faker->hexLiteral(),
                'BIN_NUM' => $this->faker->binaryLiteral(),
                'LEX_HOSTNAME' => $this->faker->hostname(),

                // Special _SYM case
                'WITH_ROLLUP_SYM' => 'WITH ROLLUP',

                // Default: strip _SYM suffix or return as-is
                default => str_ends_with($name, '_SYM')
                    ? substr($name, 0, -4)
                    : $name,
            };

            if ($token !== null) {
                $tokens[] = (string) $token;
            }
        }

        // Render tokens to SQL string
        $out = '';
        $prev = null;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            // Sanitize: MySQL throws a manual parse error for <=> ALL/ANY (subquery)
            // even though "comp_op all_or_any table_subquery" exists in sql_yacc.yy.
            if ($token === '<=>' && isset($tokens[$i + 1]) && ($tokens[$i + 1] === 'ALL' || $tokens[$i + 1] === 'ANY')) {
                $token = '=';
            }

            if ($out === '') {
                $out = $token;
                $prev = $token;
                continue;
            }

            // Determine spacing between tokens
            $needsSpace = true;

            // '@' token handling: MySQL requires no space around '@' in:
            // - User variables: @var, @'var', @`var`
            // - System variables: @@var
            // - User accounts: 'user'@'host', user@host
            // See: sql/sql_lex.cc MY_LEX_USER_VARIABLE_DELIMITER, MY_LEX_SYSTEM_VAR
            if ($prev === '@' || $token === '@') {
                $needsSpace = false;
            } elseif ($token === '(' && $prev !== null && (
                // Word + '(' must not have space for MySQL lexer to recognize function tokens.
                // See: sql/lex.h SYM_FN macro - functions like COUNT, SUM require immediate '('.
                ($prev[0] === '`' && str_ends_with($prev, '`')) ||
                preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $prev) === 1
            )) {
                $needsSpace = false;
            }

            if ($needsSpace) {
                $out .= ' ';
            }

            $out .= $token;
            $prev = $token;
        }

        return trim($out);
    }
}
