<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\PostgreSqlProvider;

/**
 * Grammar-driven SQL generator for PostgreSQL.
 *
 * Generates syntactically valid SQL strings using PostgreSQL's official grammar.
 * It implements formal grammar derivation: starting from a non-terminal symbol,
 * repeatedly replacing non-terminals with production rule right-hand sides
 * until only terminal symbols remain.
 */
final class SqlGenerator
{
    private const DERIVATION_LIMIT = 5000;

    private Grammar $grammar;
    private FakerGenerator $faker;
    private PostgreSqlProvider $provider;
    private TerminationAnalyzer $terminationAnalyzer;
    private RandomStringGenerator $rsg;

    private int $targetDepth = PHP_INT_MAX;
    private int $derivationSteps = 0;

    public function __construct(Grammar $grammar, FakerGenerator $faker, PostgreSqlProvider $provider)
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
     * @param int $targetDepth Depth at which generator starts seeking termination
     */
    public function generate(?string $startRule = null, int $targetDepth = PHP_INT_MAX): string
    {
        $this->derivationSteps = 0;
        $this->targetDepth = max(1, $targetDepth);

        $start = $startRule ?? 'stmtmulti';

        $terminals = $this->derive($start);

        return $this->render($terminals);
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
     * Handles PostgreSQL-specific terminal resolution and spacing rules.
     *
     * @param list<Terminal> $terminals
     */
    private function render(array $terminals): string
    {
        $tokens = [];
        foreach ($terminals as $terminal) {
            $name = $terminal->value;

            $token = match ($name) {
                'MODE_TYPE_NAME', 'MODE_PLPGSQL_EXPR', 'MODE_PLPGSQL_ASSIGN1',
                'MODE_PLPGSQL_ASSIGN2', 'MODE_PLPGSQL_ASSIGN3' => null,

                'TYPECAST' => '::',
                'DOT_DOT' => '..',
                'COLON_EQUALS' => ':=',
                'EQUALS_GREATER' => '=>',
                'NOT_EQUALS' => '!=',
                'LESS_EQUALS' => '<=',
                'GREATER_EQUALS' => '>=',
                'NOT_LA' => 'NOT',
                'WITH_LA' => 'WITH',
                'WITHOUT_LA' => 'WITHOUT',
                'FORMAT_LA' => 'FORMAT',
                'NULLS_LA' => 'NULLS',

                'IDENT' => $this->rsg->rawIdentifier(),
                'UIDENT' => sprintf('U&"%s"', $this->rsg->rawIdentifier()),
                'SCONST' => $this->provider->stringLiteral(),
                'USCONST' => sprintf("U&'%s'", $this->rsg->mixedAlnumString()),
                'ICONST' => $this->provider->integerLiteral(),
                'FCONST' => $this->provider->decimalLiteral(),
                'BCONST' => $this->provider->binaryLiteral(),
                'XCONST' => $this->provider->hexLiteral(),
                'Op' => $this->generateOperator(),
                'PARAM' => $this->provider->parameterMarker(),

                default => str_ends_with($name, '_P')
                    ? substr($name, 0, -2)
                    : $name,
            };

            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        $tokens = $this->truncateQualifiedNames($tokens);

        $tokens = $this->sanitizeOperatorDefList($tokens);

        $tokens = $this->sanitizeOperatorArgTypes($tokens);

        return TokenJoiner::join($tokens, [
            ['::', '*'],
            ['*', '::'],
        ]);
    }

    /**
     * @param list<string> $tokens
     */
    private static function findMatchingParen(array $tokens, int $openIndex): ?int
    {
        $depth = 0;
        $count = count($tokens);
        for ($k = $openIndex; $k < $count; $k++) {
            if ($tokens[$k] === '(') {
                $depth++;
            } elseif ($tokens[$k] === ')') {
                if (--$depth === 0) {
                    return $k;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function sanitizeOperatorDefList(array $tokens): array
    {
        $count = count($tokens);

        foreach ($tokens as $i => $tok) {
            if ($tok !== 'SET' || ($tokens[$i + 1] ?? null) !== '(') {
                continue;
            }

            $parenEnd = self::findMatchingParen($tokens, $i + 1);
            if ($parenEnd === null) {
                continue;
            }

            $depth = 1;
            for ($k = $i + 2; $k < $parenEnd; $k++) {
                if ($tokens[$k] === '(') {
                    $depth++;
                } elseif ($tokens[$k] === ')') {
                    $depth--;
                }

                if ($depth === 1
                    && TokenJoiner::isIdentifier($tokens[$k])
                    && (($tokens[$k + 1] ?? null) === ',' || ($tokens[$k + 1] ?? null) === ')')
                    && ($tokens[$k - 1] ?? null) !== '='
                ) {
                    array_splice($tokens, $k + 1, 0, ['=', 'NONE']);
                    $count += 2;
                    $parenEnd += 2;
                    $k += 2;
                }
            }
        }

        return $tokens;
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function truncateQualifiedNames(array $tokens): array
    {
        $result = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!TokenJoiner::isIdentifier($token) || ($tokens[$i + 1] ?? null) !== '.') {
                $result[] = $token;
                continue;
            }

            /** @var list<string> $chain */
            $chain = [$token];
            while ($i + 1 < $count && $tokens[$i + 1] === '.' && isset($tokens[$i + 2])) {
                $following = $tokens[$i + 2];
                if ($following === '*') {
                    $i += 2;
                    break;
                }
                if (!TokenJoiner::isIdentifier($following)) {
                    break;
                }
                $chain[] = '.';
                $chain[] = $following;
                $i += 2;
            }

            array_push($result, ...array_slice($chain, 0, 5));
        }

        return $result;
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function sanitizeOperatorArgTypes(array $tokens): array
    {
        $count = count($tokens);

        foreach ($tokens as $i => $tok) {
            if ($tok !== 'OPERATOR' || ($tokens[$i + 1] ?? null) === '(') {
                continue;
            }

            $slice = array_slice($tokens, $i + 1);
            $offset = array_search('(', $slice, true);
            if ($offset === false) {
                continue;
            }
            $parenStart = $i + 1 + $offset;

            $parenEnd = self::findMatchingParen($tokens, $parenStart);
            if ($parenEnd === null) {
                continue;
            }

            if ($parenEnd - $parenStart === 2 && $tokens[$parenStart + 1] !== ',') {
                array_splice($tokens, $parenStart + 1, 0, ['NONE', ',']);
                $count += 2;
            }
        }

        return $tokens;
    }

    private function generateOperator(): string
    {
        /** @var string $op */
        $op = $this->faker->randomElement(['+', '-', '*', '/', '<', '>', '=', '~', '!', '@', '#', '%', '^', '&', '|']);
        return $op;
    }
}
