<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use RuntimeException;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar as LexicalGrammarContract;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TokenJoiner;

/**
 * MySQL lexical realization for one exact server version and the default sql_mode.
 */
final class LexicalGrammar implements LexicalGrammarContract
{
    /** @var array<string, list<string>> */
    private array $symbols;

    /** @var array<string, list<string>> */
    private array $functions;

    /** @var array<string, string> */
    private array $symbolTokens;

    /** @var array<string, string> */
    private array $functionTokens;

    private bool $dollarQuotedStrings;
    private RandomStringGenerator $strings;
    private LexicalCatalog $catalog;

    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly string $profileVersion,
        private readonly bool $allowSyntheticTerminals = false,
    ) {
        $path = SqlVersion::resolve('mysql', $profileVersion)->lexicalPath;
        if (!file_exists($path)) {
            throw new RuntimeException("Lexical profile file not found: {$path}");
        }

        /** @var array{dialect: string, version: string, symbols: array<string, list<string>>, functions: array<string, list<string>>, features: array{dollar_quoted_strings: bool}, catalog: array<string, mixed>} $profile */
        $profile = require $path;
        if ($profile['dialect'] !== 'mysql' || $profile['version'] !== $profileVersion) {
            throw new RuntimeException("Invalid MySQL lexical profile: {$path}");
        }

        $this->symbols = $profile['symbols'];
        $this->functions = $profile['functions'];
        $this->symbolTokens = $this->reverse($this->symbols);
        $this->functionTokens = $this->reverse($this->functions);
        $this->dollarQuotedStrings = $profile['features']['dollar_quoted_strings'];
        $this->strings = new RandomStringGenerator($faker);
        $this->catalog = new LexicalCatalog($profile['catalog']);
    }

    public function version(): string
    {
        return $this->profileVersion;
    }

    public function supports(string $terminal): bool
    {
        return $this->allowSyntheticTerminals || $this->catalog->supports($terminal);
    }

    /**
     * @param list<string> $terminals
     */
    public function assertTerminalsCovered(array $terminals): void
    {
        $this->catalog->assertTerminalsCovered($terminals);
    }

    /**
     * @param list<string> $terminals
     * @param GenerationPlan<bool>|null $plan
     */
    public function realize(array $terminals, ?GenerationPlan $plan = null): string
    {
        $lexemes = [];
        $expected = [];
        /** @var array<string, int> $occurrences */
        $occurrences = [];
        foreach ($terminals as $terminal) {
            $occurrence = $occurrences[$terminal] ?? 0;
            $occurrences[$terminal] = $occurrence + 1;
            [$lexeme, $tokens] = $this->realizeTerminal($terminal, $plan?->lexemeAt($terminal, $occurrence));
            if ($lexeme !== null) {
                $lexemes[] = $lexeme;
            }
            array_push($expected, ...$tokens);
        }

        $sql = TokenJoiner::join(
            $lexemes,
            [['@', '*'], ['*', '@']],
            fn (): string => $this->trivia(),
            fn (): string => $this->optionalTrivia(),
        );
        $actual = $this->tokenize($sql);
        if ($this->allowSyntheticTerminals) {
            $expected = $actual;
        }
        if ($actual !== $expected) {
            throw new LexicalException($this->roundTripMessage($expected, $actual, $sql));
        }

        return $sql;
    }

    /**
     * @return list<string>
     */
    public function tokenize(string $sql): array
    {
        $tokens = [];
        $length = strlen($sql);
        $offset = 0;

        while ($offset < $length) {
            if ($this->skipTrivia($sql, $offset)) {
                continue;
            }

            $char = $sql[$offset];
            if ($char === '`') {
                $this->skipQuoted($sql, $offset, '`');
                $tokens[] = 'IDENT_QUOTED';
                continue;
            }
            if ($char === "'") {
                $this->skipQuoted($sql, $offset, "'");
                $tokens[] = 'TEXT_STRING';
                continue;
            }
            if ($char === '$' && $this->dollarQuotedStrings && substr($sql, $offset, 2) === '$$') {
                $end = strpos($sql, '$$', $offset + 2);
                if ($end === false) {
                    throw new LexicalException('Unterminated MySQL dollar-quoted string.');
                }
                $offset = $end + 2;
                $tokens[] = 'DOLLAR_QUOTED_STRING_SYM';
                continue;
            }
            if ($char === '?') {
                $offset++;
                $tokens[] = 'PARAM_MARKER';
                continue;
            }
            if ($char === '@') {
                $offset++;
                $tokens[] = '@';
                continue;
            }
            if (($tokens[count($tokens) - 1] ?? null) === '@'
                && preg_match('/\G[A-Za-z0-9_.%-]+/A', $sql, $match, 0, $offset) === 1
            ) {
                $offset += strlen($match[0]);
                $tokens[] = 'LEX_HOSTNAME';
                continue;
            }
            if (preg_match('/\G(?:0[xX][0-9A-Fa-f]+|0[bB][01]+)/A', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = strtolower(substr($match[0], 0, 2)) === '0x' ? 'HEX_NUM' : 'BIN_NUM';
                continue;
            }
            if (preg_match('/\G(?:[nN]|[xX]|[bB])\'/A', $sql, $match, 0, $offset) === 1) {
                $prefix = strtoupper($match[0][0]);
                $offset++;
                $this->skipQuoted($sql, $offset, "'");
                $tokens[] = match ($prefix) {
                    'N' => 'NCHAR_STRING',
                    'X' => 'HEX_NUM',
                    default => 'BIN_NUM',
                };
                continue;
            }
            if (preg_match('/\G(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?|\G\d+[eE][+-]?\d+/A', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = str_contains(strtolower($match[0]), 'e') ? 'FLOAT_NUM' : 'DECIMAL_NUM';
                continue;
            }
            if (preg_match('/\G\d+/A', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = $this->integerToken($match[0]);
                continue;
            }
            if (preg_match('/\G_[A-Za-z0-9_]*/A', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = strtolower($match[0]) === '_utf8mb4' ? 'UNDERSCORE_CHARSET' : 'IDENT';
                continue;
            }
            if (preg_match('/\G[A-Za-z][A-Za-z0-9_$]*/A', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $word = strtoupper($match[0]);
                $token = ($sql[$offset] ?? null) === '(' ? ($this->functionTokens[$word] ?? null) : null;
                $tokens[] = $token ?? $this->symbolTokens[$word] ?? 'IDENT';
                continue;
            }

            $operator = $this->operatorAt($sql, $offset);
            if ($operator !== null) {
                $offset += strlen($operator);
                $token = $this->symbolTokens[$operator] ?? $operator;
                $tokens[] = $token === 'OR_OR_SYM' ? 'OR2_SYM' : $token;
                continue;
            }

            throw new LexicalException("Unsupported MySQL lexical input at offset {$offset}: {$sql}");
        }

        for ($index = 0; $index + 1 < count($tokens); $index++) {
            if ($tokens[$index] === 'WITH' && $tokens[$index + 1] === 'ROLLUP_SYM') {
                array_splice($tokens, $index, 2, ['WITH_ROLLUP_SYM']);
            }
        }

        return $tokens;
    }

    /**
     * @param non-empty-string|null $requestedLexeme
     * @return array{string|null, list<string>}
     */
    private function realizeTerminal(string $terminal, ?string $requestedLexeme = null): array
    {
        if (!$this->supports($terminal)) {
            throw new LexicalException("Unsupported MySQL terminal for {$this->profileVersion}: {$terminal}");
        }

        if ($requestedLexeme !== null) {
            return $this->realizeRequestedLexeme($terminal, $requestedLexeme);
        }

        if (!$this->allowSyntheticTerminals) {
            $witnesses = $this->catalog->witnesses($terminal);
            $witness = $witnesses[$this->faker->numberBetween(0, count($witnesses) - 1)];

            return [$witness['sql'] === '' ? null : $witness['sql'], $witness['tokens']];
        }
        if (str_starts_with($terminal, 'GRAMMAR_SELECTOR_') || $terminal === 'END_OF_INPUT') {
            return [null, []];
        }

        return match ($terminal) {
            'IDENT' => [$this->identifier(), ['IDENT']],
            'IDENT_QUOTED' => [$this->quotedIdentifier(), ['IDENT_QUOTED']],
            'TEXT_STRING' => [$this->stringLiteral(), ['TEXT_STRING']],
            'NCHAR_STRING' => ['N' . $this->stringLiteral(), ['NCHAR_STRING']],
            'DOLLAR_QUOTED_STRING_SYM' => [$this->dollarQuotedString(), [$terminal]],
            'NUM' => [$this->strings->integerString(0, 2147483647), ['NUM']],
            'LONG_NUM' => [(string) $this->faker->numberBetween(2147483648, PHP_INT_MAX), ['LONG_NUM']],
            'ULONGLONG_NUM' => ['18446744073709551615', ['ULONGLONG_NUM']],
            'DECIMAL_NUM' => [$this->strings->decimalString(), ['DECIMAL_NUM']],
            'FLOAT_NUM' => [$this->strings->floatString($this->strings->integerString()), ['FLOAT_NUM']],
            'HEX_NUM' => [$this->hexadecimalLiteral(), ['HEX_NUM']],
            'BIN_NUM' => [$this->binaryLiteral(), ['BIN_NUM']],
            'LEX_HOSTNAME' => ['localhost', ['IDENT']],
            'PARAM_MARKER' => ['?', ['PARAM_MARKER']],
            'OR2_SYM' => ['||', ['OR2_SYM']],
            'WITH_ROLLUP_SYM' => ['WITH ROLLUP', ['WITH_ROLLUP_SYM']],
            'UNDERSCORE_CHARSET' => ['_utf8mb4', ['UNDERSCORE_CHARSET']],
            default => $this->fixedTerminal($terminal),
        };
    }

    /**
     * @param non-empty-string $requestedLexeme
     * @return array{non-empty-string, list<string>}
     */
    private function realizeRequestedLexeme(string $terminal, string $requestedLexeme): array
    {
        if ($this->allowSyntheticTerminals) {
            $tokens = $this->tokenize($requestedLexeme);
            if ($tokens !== [$terminal]) {
                throw new LexicalException("Requested MySQL lexeme does not realize {$terminal}: {$requestedLexeme}");
            }

            return [$requestedLexeme, $tokens];
        }

        foreach ($this->catalog->witnesses($terminal) as $witness) {
            if ($witness['sql'] === $requestedLexeme) {
                return [$requestedLexeme, $witness['tokens']];
            }
        }

        throw new LexicalException("MySQL lexical catalog has no {$terminal} witness for: {$requestedLexeme}");
    }

    /**
     * @return array{string, list<string>}
     */
    private function fixedTerminal(string $terminal): array
    {
        if ($this->allowSyntheticTerminals) {
            $lexeme = match ($terminal) {
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
                'OR_OR_SYM', 'OR2_SYM' => '||',
                'NOT2_SYM' => 'NOT',
                'SET_VAR' => ':=',
                'JSON_SEPARATOR_SYM' => '->',
                'JSON_UNQUOTED_SEPARATOR_SYM' => '->>',
                'NEG' => '-',
                default => str_ends_with($terminal, '_SYM') ? substr($terminal, 0, -4) : $terminal,
            };

            return [$lexeme, $this->tokenize($lexeme)];
        }

        $lexemes = $this->symbols[$terminal] ?? $this->functions[$terminal] ?? null;
        $lexeme = $lexemes !== null
            ? $lexemes[$this->faker->numberBetween(0, count($lexemes) - 1)]
            : $terminal;

        return [$lexeme, isset($this->symbols[$terminal]) || isset($this->functions[$terminal])
            ? [$terminal]
            : $this->tokenize($lexeme)];
    }

    private function identifier(): string
    {
        return '_' . $this->strings->rawIdentifier();
    }

    private function quotedIdentifier(): string
    {
        $body = $this->faker->numberBetween(0, 3) === 0 ? 'select' : $this->identifier();
        if ($this->faker->numberBetween(0, 7) === 0) {
            $body .= '`' . $this->strings->rawIdentifier();
        }

        return '`' . str_replace('`', '``', $body) . '`';
    }

    private function stringLiteral(): string
    {
        $body = match ($this->faker->numberBetween(0, 6)) {
            0 => 'SELECT FROM WHERE',
            1 => '/* UPDATE */ -- DELETE',
            2 => "a'b",
            3 => 'a\\b',
            default => $this->strings->mixedAlnumString(0, 24),
        };

        return "'" . str_replace("'", "''", $body) . "'";
    }

    private function dollarQuotedString(): string
    {
        return '$$' . str_replace('$$', '$', $this->strings->mixedAlnumString(0, 24)) . '$$';
    }

    private function hexadecimalLiteral(): string
    {
        if ($this->faker->numberBetween(0, 1) === 0) {
            return '0x' . $this->strings->hexString();
        }

        $length = $this->faker->numberBetween(0, 8) * 2;

        return "X'" . $this->strings->hexString($length, $length) . "'";
    }

    private function binaryLiteral(): string
    {
        if ($this->faker->numberBetween(0, 1) === 0) {
            return '0b' . $this->strings->binaryString();
        }

        return "B'" . $this->strings->binaryString(0, 32) . "'";
    }

    private function trivia(): string
    {
        if ($this->allowSyntheticTerminals) {
            return ' ';
        }

        $witnesses = $this->catalog->witnesses('@TRIVIA');

        return $witnesses[$this->faker->numberBetween(0, count($witnesses) - 1)]['sql'];
    }

    private function optionalTrivia(): string
    {
        if ($this->allowSyntheticTerminals || $this->faker->numberBetween(0, 1) === 0) {
            return '';
        }

        return $this->trivia();
    }

    private function skipTrivia(string $sql, int &$offset): bool
    {
        if (preg_match('/\G\s+/A', $sql, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return true;
        }
        if (substr($sql, $offset, 2) === '/*') {
            $end = strpos($sql, '*/', $offset + 2);
            if ($end === false) {
                throw new LexicalException('Unterminated MySQL block comment.');
            }
            $offset = $end + 2;

            return true;
        }
        if ($sql[$offset] === '#' || (substr($sql, $offset, 2) === '--' && preg_match('/\s/', $sql[$offset + 2] ?? '') === 1)) {
            $end = strpos($sql, "\n", $offset + 1);
            $offset = $end === false ? strlen($sql) : $end + 1;

            return true;
        }

        return false;
    }

    private function skipQuoted(string $sql, int &$offset, string $quote): void
    {
        $length = strlen($sql);
        $offset++;
        while ($offset < $length) {
            if ($sql[$offset] !== $quote) {
                $offset++;
                continue;
            }
            if (($sql[$offset + 1] ?? null) === $quote) {
                $offset += 2;
                continue;
            }
            $offset++;

            return;
        }

        throw new LexicalException("Unterminated MySQL quoted token: {$sql}");
    }

    private function integerToken(string $integer): string
    {
        $normalized = ltrim($integer, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        if (strlen($normalized) < 10 || (strlen($normalized) === 10 && strcmp($normalized, '2147483647') <= 0)) {
            return 'NUM';
        }
        if (strlen($normalized) < 19 || (strlen($normalized) === 19 && strcmp($normalized, '9223372036854775807') <= 0)) {
            return 'LONG_NUM';
        }

        return 'ULONGLONG_NUM';
    }

    private function operatorAt(string $sql, int $offset): ?string
    {
        foreach (['<=>', '->>', '&&', '<=', '<>', '!=', '>=', '<<', '>>', ':=', '->', '||'] as $operator) {
            if (substr($sql, $offset, strlen($operator)) === $operator) {
                return $operator;
            }
        }
        $char = $sql[$offset];

        return $this->isPunctuation($char) ? $char : null;
    }

    private function isPunctuation(string $terminal): bool
    {
        return strlen($terminal) === 1 && str_contains('!%&()*+,-./:;@^{}|~=<>', $terminal);
    }

    /**
     * @param array<string, list<string>> $tokens
     * @return array<string, string>
     */
    private function reverse(array $tokens): array
    {
        $result = [];
        foreach ($tokens as $token => $lexemes) {
            foreach ($lexemes as $lexeme) {
                $result[strtoupper($lexeme)] = $token;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private function roundTripMessage(array $expected, array $actual, string $sql): string
    {
        return sprintf(
            "MySQL lexical round-trip failed for %s.\nExpected: %s\nActual: %s\nSQL: %s",
            $this->profileVersion,
            json_encode($expected, JSON_THROW_ON_ERROR),
            json_encode($actual, JSON_THROW_ON_ERROR),
            $sql,
        );
    }
}
