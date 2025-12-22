<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Symbol;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;
use SqlFaker\MySqlProvider;

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
    private MySqlProvider $provider;
    private TerminationAnalyzer $terminationAnalyzer;
    private RandomStringGenerator $rsg;

    private int $targetDepth = PHP_INT_MAX;
    private int $derivationSteps = 0;

    public function __construct(Grammar $grammar, FakerGenerator $faker, MySqlProvider $provider)
    {
        $this->grammar = $grammar;
        $this->faker = $faker;
        $this->provider = $provider;
        $this->terminationAnalyzer = new TerminationAnalyzer($grammar);
        $this->rsg = new RandomStringGenerator($faker);
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
            $rule = $this->grammar->ruleMap[$nonTerminal->value] ?? throw new LogicException("Unknown grammar rule: {$nonTerminal->value}");
            $alternatives = $rule->alternatives;

            if ($alternatives === []) {
                throw new LogicException('Production rule has no alternatives.');
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
        $tokens = [];
        foreach ($terminals as $terminal) {
            $name = $terminal->value;

            $token = match ($name) {
                'END_OF_INPUT' => null,

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

                'IDENT' => $this->rsg->rawIdentifier(),
                'IDENT_QUOTED' => '`' . $this->rsg->rawIdentifier() . '`',
                'TEXT_STRING' => $this->provider->stringLiteral(),
                'NCHAR_STRING' => $this->provider->nationalStringLiteral(),
                'DOLLAR_QUOTED_STRING_SYM' => $this->provider->dollarQuotedString(),
                'NUM' => $this->provider->integerLiteral(),
                'LONG_NUM' => $this->provider->longIntegerLiteral(),
                'ULONGLONG_NUM' => $this->provider->unsignedBigIntLiteral(),
                'DECIMAL_NUM' => $this->provider->decimalLiteral(),
                'FLOAT_NUM' => $this->provider->floatLiteral(),
                'HEX_NUM' => $this->provider->hexLiteral(),
                'BIN_NUM' => $this->provider->binaryLiteral(),
                'LEX_HOSTNAME' => $this->provider->hostname(),

                'WITH_ROLLUP_SYM' => 'WITH ROLLUP',

                default => str_ends_with($name, '_SYM')
                    ? substr($name, 0, -4)
                    : $name,
            };

            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        $tokens = $this->sanitizeTokens($tokens);

        return TokenJoiner::join($tokens, [['@', '*'], ['*', '@']]);
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function sanitizeTokens(array $tokens): array
    {
        $tokens = $this->sanitizeDotAndAtTokens($tokens);

        foreach ($tokens as $j => $tok) {
            if ($tok === 'CURRENT_USER'
                && ($tokens[$j + 1] ?? null) === '('
                && ($tokens[$j + 2] ?? null) === ')'
                && ($tokens[$j + 3] ?? null) === ':'
            ) {
                array_splice($tokens, $j + 1, 2);
            }
        }

        $eventPos = array_search('EVENT', $tokens, true);
        if ($eventPos !== false && in_array('ALTER', $tokens, true)) {
            $afterName = $eventPos + 2;
            $cnt = count($tokens);
            if ($afterName < $cnt && $tokens[$afterName] === '.' && isset($tokens[$afterName + 1])) {
                $afterName += 2;
            }
            if ($afterName >= $cnt) {
                $tokens[] = 'ENABLE';
            }
        }

        /** @var list<string> $result */
        $result = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $prev = $result !== [] ? $result[count($result) - 1] : null;

            if ($token === '<=>' && isset($tokens[$i + 1]) && ($tokens[$i + 1] === 'ALL' || $tokens[$i + 1] === 'ANY')) {
                $token = '=';
            }

            if ($token === 'RELEASE' && $prev === 'CHAIN'
                && ($result[count($result) - 2] ?? null) !== 'NO') {
                continue;
            }

            if (($prev === ':' || $prev === 'SYSTEM') && preg_match('/^\d+\.\d+/', $token) === 1) {
                $token = substr($token, 0, (int) strpos($token, '.'));
            }

            $result[] = $token;
        }

        return $result;
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function sanitizeDotAndAtTokens(array $tokens): array
    {
        $remove = [];
        foreach ($tokens as $j => $tok) {
            if ($tok !== '@') {
                continue;
            }
            for ($k = $j - 2; $k >= 1 && $tokens[$k] === '.'; $k -= 2) {
                $remove[$k] = true;
                $remove[$k - 1] = true;
            }
        }
        if ($remove !== []) {
            $tokens = array_values(array_diff_key($tokens, $remove));
        }

        foreach ($tokens as $j => $tok) {
            if (($tokens[$j + 1] ?? null) === '@') {
                $dotPos = strrpos($tok, '.');
                if ($dotPos !== false) {
                    $tokens[$j] = substr($tok, $dotPos + 1);
                }
            } elseif (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\./', $tok) === 1) {
                $tokens[$j] = substr($tok, 0, (int) strpos($tok, '.'));
            }
        }

        return $tokens;
    }
}
