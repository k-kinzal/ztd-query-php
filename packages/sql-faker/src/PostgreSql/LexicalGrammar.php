<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Faker\Generator as FakerGenerator;
use RuntimeException;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar as LexicalGrammarContract;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TokenJoiner;

/**
 * PostgreSQL lexical realization for one exact server version.
 */
final class LexicalGrammar implements LexicalGrammarContract
{
    private const OPERATOR_CHARACTERS = '+-*/<>=~!@#%^&|`?';

    /** @var array<string, list<string>> */
    private array $keywords;

    /** @var array<string, string> */
    private array $keywordTokens;

    /** @var array<string, array{token: string, followed_by: list<string>}> */
    private array $lookahead;

    private RandomStringGenerator $strings;
    private LexicalCatalog $catalog;

    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly string $profileVersion,
        private readonly bool $allowSyntheticTerminals = false,
    ) {
        $path = SqlVersion::resolve('postgresql', $profileVersion)->lexicalPath;
        if (!file_exists($path)) {
            throw new RuntimeException("Lexical profile file not found: {$path}");
        }

        /** @var array{dialect: string, version: string, keywords: array<string, list<string>>, lookahead: array<string, array{token: string, followed_by: list<string>}>, catalog: array<string, mixed>} $profile */
        $profile = require $path;
        if ($profile['dialect'] !== 'postgresql' || $profile['version'] !== $profileVersion) {
            throw new RuntimeException("Invalid PostgreSQL lexical profile: {$path}");
        }

        $this->keywords = $profile['keywords'];
        $this->lookahead = $profile['lookahead'];
        $this->keywordTokens = $this->reverse($this->keywords);
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

    public function realize(array $terminals): string
    {
        $lexemes = [];
        $expected = [];
        foreach ($terminals as $terminal) {
            [$lexeme, $tokens] = $this->realizeTerminal($terminal);
            if ($lexeme !== null) {
                $lexemes[] = $lexeme;
            }
            array_push($expected, ...$tokens);
        }

        $sql = TokenJoiner::join(
            $lexemes,
            [['::', '*'], ['*', '::']],
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
            if ($char === '"') {
                $this->skipQuoted($sql, $offset, '"');
                $tokens[] = 'IDENT';
                continue;
            }
            if ($char === "'") {
                $this->skipQuoted($sql, $offset, "'");
                $tokens[] = 'SCONST';
                continue;
            }
            if (preg_match('/\G[Uu]&"/A', $sql, $match, 0, $offset) === 1) {
                $offset += 2;
                $this->skipQuoted($sql, $offset, '"');
                $tokens[] = 'IDENT';
                continue;
            }
            if (preg_match('/\G[Uu]&\'/A', $sql, $match, 0, $offset) === 1) {
                $offset += 2;
                $this->skipQuoted($sql, $offset, "'");
                $tokens[] = 'SCONST';
                continue;
            }
            if (preg_match('/\G[Ee]\'/A', $sql, $match, 0, $offset) === 1) {
                $offset++;
                $this->skipQuoted($sql, $offset, "'");
                $tokens[] = 'SCONST';
                continue;
            }
            if (preg_match('/\G[Bb]\'/A', $sql, $match, 0, $offset) === 1) {
                $offset++;
                $this->skipQuoted($sql, $offset, "'");
                $tokens[] = 'BCONST';
                continue;
            }
            if (preg_match('/\G[Xx]\'/A', $sql, $match, 0, $offset) === 1) {
                $offset++;
                $this->skipQuoted($sql, $offset, "'");
                $tokens[] = 'XCONST';
                continue;
            }
            if (preg_match('/\G\$(?:[A-Za-z_][A-Za-z0-9_]*)?\$/A', $sql, $match, 0, $offset) === 1) {
                $delimiter = $match[0];
                $end = strpos($sql, $delimiter, $offset + strlen($delimiter));
                if ($end === false) {
                    throw new LexicalException('Unterminated PostgreSQL dollar-quoted string.');
                }
                $offset = $end + strlen($delimiter);
                $tokens[] = 'SCONST';
                continue;
            }
            if (preg_match('/\G\$[1-9][0-9]*/A', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = 'PARAM';
                continue;
            }
            if (preg_match('/\G(?:\d+\.\d*|\.\d+|\d+)[eE][+-]?\d+|\G(?:\d+\.\d*|\.\d+)/A', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = 'FCONST';
                continue;
            }
            if (preg_match('/\G\d+/A', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = 'ICONST';
                continue;
            }
            if (preg_match('/\G[A-Za-z_][A-Za-z0-9_$]*/A', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $word = strtoupper($match[0]);
                $tokens[] = $this->keywordTokens[$word] ?? 'IDENT';
                continue;
            }

            $operator = $this->operatorAt($sql, $offset);
            if ($operator !== null) {
                $offset += strlen($operator[0]);
                $tokens[] = $operator[1];
                continue;
            }

            throw new LexicalException("Unsupported PostgreSQL lexical input at offset {$offset}: {$sql}");
        }

        foreach ($tokens as $index => $token) {
            $rule = $this->lookahead[$token] ?? null;
            if ($rule !== null && in_array($tokens[$index + 1] ?? null, $rule['followed_by'], true)) {
                $tokens[$index] = $rule['token'];
            }
        }

        return $tokens;
    }

    /**
     * @return array{string|null, list<string>}
     */
    private function realizeTerminal(string $terminal): array
    {
        if (!$this->supports($terminal)) {
            throw new LexicalException("Unsupported PostgreSQL terminal for {$this->profileVersion}: {$terminal}");
        }

        if (!$this->allowSyntheticTerminals) {
            $witnesses = $this->catalog->witnesses($terminal);
            $witness = $witnesses[$this->faker->numberBetween(0, count($witnesses) - 1)];

            return [$witness['sql'] === '' ? null : $witness['sql'], $witness['tokens']];
        }
        if (str_starts_with($terminal, 'MODE_')) {
            return [null, []];
        }

        return match ($terminal) {
            'IDENT' => [$this->identifier(), ['IDENT']],
            'UIDENT' => [$this->quotedIdentifier(true), ['UIDENT']],
            'SCONST' => [$this->stringLiteral(), ['SCONST']],
            'USCONST' => [$this->unicodeStringLiteral(), ['USCONST']],
            'ICONST' => [$this->strings->integerString(0, 2147483647), ['ICONST']],
            'FCONST' => [$this->decimalLiteral(), ['FCONST']],
            'BCONST' => ["B'" . $this->strings->binaryString(0, 32) . "'", ['BCONST']],
            'XCONST' => ["X'" . $this->strings->hexString(0, 16) . "'", ['XCONST']],
            'Op' => [$this->operator(), ['Op']],
            'PARAM' => ['$' . $this->faker->numberBetween(1, 10), ['PARAM']],
            'TYPECAST' => ['::', ['TYPECAST']],
            'DOT_DOT' => ['..', ['DOT_DOT']],
            'COLON_EQUALS' => [':=', ['COLON_EQUALS']],
            'EQUALS_GREATER' => ['=>', ['EQUALS_GREATER']],
            'NOT_EQUALS' => [$this->faker->numberBetween(0, 1) === 0 ? '<>' : '!=', ['NOT_EQUALS']],
            'LESS_EQUALS' => ['<=', ['LESS_EQUALS']],
            'GREATER_EQUALS' => ['>=', ['GREATER_EQUALS']],
            default => $this->fixedTerminal($terminal),
        };
    }

    /**
     * @return array{string, list<string>}
     */
    private function fixedTerminal(string $terminal): array
    {
        $lookaheadBase = null;
        foreach ($this->lookahead as $base => $rule) {
            if ($rule['token'] === $terminal) {
                $lookaheadBase = $base;
                break;
            }
        }

        $lexemes = $this->keywords[$lookaheadBase ?? $terminal] ?? null;
        $lexeme = $lexemes !== null
            ? $lexemes[$this->faker->numberBetween(0, count($lexemes) - 1)]
            : (str_ends_with($terminal, '_P') ? substr($terminal, 0, -2) : $terminal);

        return [$lexeme, $lexemes !== null ? [$terminal] : $this->tokenize($lexeme)];
    }

    private function identifier(): string
    {
        if ($this->faker->numberBetween(0, 3) === 0) {
            return $this->quotedIdentifier(false);
        }

        return '_' . $this->strings->rawIdentifier();
    }

    private function quotedIdentifier(bool $unicode): string
    {
        $body = $this->faker->numberBetween(0, 3) === 0 ? 'values' : '_' . $this->strings->rawIdentifier();
        if ($this->faker->numberBetween(0, 7) === 0) {
            $body .= '"' . $this->strings->rawIdentifier();
        }

        return ($unicode ? 'U&' : '') . '"' . str_replace('"', '""', $body) . '"';
    }

    private function stringLiteral(): string
    {
        return match ($this->faker->numberBetween(0, 3)) {
            0 => "E'a\\\\b'",
            1 => $this->dollarQuotedString(),
            default => $this->standardStringLiteral(),
        };
    }

    private function standardStringLiteral(): string
    {
        $body = match ($this->faker->numberBetween(0, 4)) {
            0 => 'SELECT FROM WHERE',
            1 => '/* UPDATE */ -- DELETE',
            2 => "a'b",
            default => $this->strings->mixedAlnumString(0, 24),
        };

        return "'" . str_replace("'", "''", $body) . "'";
    }

    private function dollarQuotedString(): string
    {
        $tag = $this->faker->numberBetween(0, 1) === 0 ? '' : $this->strings->rawIdentifier(1, 8);
        $delimiter = '$' . $tag . '$';
        $body = str_replace($delimiter, '$', 'SELECT ? FROM /* UPDATE */ ' . $this->strings->mixedAlnumString(0, 12));

        return $delimiter . $body . $delimiter;
    }

    private function unicodeStringLiteral(): string
    {
        return "U&'" . $this->strings->mixedAlnumString(0, 12) . "'";
    }

    private function decimalLiteral(): string
    {
        return match ($this->faker->numberBetween(0, 3)) {
            0 => '.5',
            1 => '1.',
            2 => '1e-1',
            default => $this->strings->decimalString(),
        };
    }

    private function operator(): string
    {
        $common = ['?', '?|', '?&'];
        $commonIndex = $this->faker->numberBetween(0, 7);
        if (isset($common[$commonIndex])) {
            return $common[$commonIndex];
        }

        do {
            $length = $this->faker->numberBetween(2, 4);
            $operator = '';
            for ($index = 0; $index < $length; $index++) {
                $operator .= self::OPERATOR_CHARACTERS[$this->faker->numberBetween(0, strlen(self::OPERATOR_CHARACTERS) - 1)];
            }
        } while (str_contains($operator, '--') || str_contains($operator, '/*') || $this->fixedOperator($operator) !== null);

        if (($operator[strlen($operator) - 1] === '+' || $operator[strlen($operator) - 1] === '-')
            && preg_match('/[~!@#%^&|`?]/', $operator) !== 1
        ) {
            $operator[strlen($operator) - 1] = '@';
        }

        return $operator;
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
        if (substr($sql, $offset, 2) === '--') {
            $end = strpos($sql, "\n", $offset + 2);
            $offset = $end === false ? strlen($sql) : $end + 1;

            return true;
        }
        if (substr($sql, $offset, 2) !== '/*') {
            return false;
        }

        $depth = 1;
        $offset += 2;
        while ($offset < strlen($sql) && $depth > 0) {
            if (substr($sql, $offset, 2) === '/*') {
                $depth++;
                $offset += 2;
            } elseif (substr($sql, $offset, 2) === '*/') {
                $depth--;
                $offset += 2;
            } else {
                $offset++;
            }
        }
        if ($depth !== 0) {
            throw new LexicalException('Unterminated PostgreSQL block comment.');
        }

        return true;
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

        throw new LexicalException("Unterminated PostgreSQL quoted token: {$sql}");
    }

    /**
     * @return array{string, string}|null
     */
    private function operatorAt(string $sql, int $offset): ?array
    {
        foreach (['::', '..', ':=', '=>', '<=', '>=', '<>', '!='] as $operator) {
            if (substr($sql, $offset, strlen($operator)) === $operator) {
                return [$operator, $this->fixedOperator($operator) ?? 'Op'];
            }
        }

        $char = $sql[$offset];
        if (str_contains(self::OPERATOR_CHARACTERS, $char)) {
            $end = $offset;
            while (isset($sql[$end]) && str_contains(self::OPERATOR_CHARACTERS, $sql[$end])) {
                $end++;
            }
            $lexeme = substr($sql, $offset, $end - $offset);

            return strlen($lexeme) === 1 && $this->isPunctuation($lexeme)
                ? [$lexeme, $lexeme]
                : [$lexeme, 'Op'];
        }

        return $this->isPunctuation($char) ? [$char, $char] : null;
    }

    private function fixedOperator(string $operator): ?string
    {
        return match ($operator) {
            '::' => 'TYPECAST',
            '..' => 'DOT_DOT',
            ':=' => 'COLON_EQUALS',
            '=>' => 'EQUALS_GREATER',
            '<>', '!=' => 'NOT_EQUALS',
            '<=' => 'LESS_EQUALS',
            '>=' => 'GREATER_EQUALS',
            default => null,
        };
    }

    private function isPunctuation(string $terminal): bool
    {
        return strlen($terminal) === 1 && str_contains('%()*+,-./:;<=>[]^', $terminal);
    }

    /**
     * @param array<string, list<string>> $keywords
     * @return array<string, string>
     */
    private function reverse(array $keywords): array
    {
        $result = [];
        foreach ($keywords as $token => $lexemes) {
            foreach ($lexemes as $lexeme) {
                $result[$lexeme] = $token;
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
            "PostgreSQL lexical round-trip failed for %s.\nExpected: %s\nActual: %s\nSQL: %s",
            $this->profileVersion,
            json_encode($expected, JSON_THROW_ON_ERROR),
            json_encode($actual, JSON_THROW_ON_ERROR),
            $sql,
        );
    }
}
