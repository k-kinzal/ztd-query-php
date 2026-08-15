<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use RuntimeException;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar as LexicalGrammarContract;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TokenJoiner;

/**
 * SQLite lexical realization for one exact release.
 */
final class LexicalGrammar implements LexicalGrammarContract
{
    /** @var array<string, list<string>> */
    private array $keywords;

    /** @var array<string, string> */
    private array $keywordTokens;

    private RandomStringGenerator $strings;
    private LexicalCatalog $catalog;

    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly string $profileVersion,
        private readonly bool $allowSyntheticTerminals = false,
    ) {
        $path = SqlVersion::resolve('sqlite', $profileVersion)->lexicalPath;
        if (!file_exists($path)) {
            throw new RuntimeException("Lexical profile file not found: {$path}");
        }

        /** @var array{dialect: string, version: string, keywords: array<string, list<string>>, catalog: array<string, mixed>} $profile */
        $profile = require $path;
        if ($profile['dialect'] !== 'sqlite' || $profile['version'] !== $profileVersion) {
            throw new RuntimeException("Invalid SQLite lexical profile: {$path}");
        }

        $this->keywords = $profile['keywords'];
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
            $lexemes[] = $lexeme;
            array_push($expected, ...$tokens);
        }

        $sql = TokenJoiner::join(
            $lexemes,
            [['->', '*'], ['*', '->']],
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
            if ($char === "'") {
                $this->skipQuoted($sql, $offset, "'");
                $tokens[] = 'STRING';
                continue;
            }
            if ($char === '"' || $char === '`') {
                $this->skipQuoted($sql, $offset, $char);
                $tokens[] = 'ID';
                continue;
            }
            if ($char === '[') {
                $end = strpos($sql, ']', $offset + 1);
                if ($end === false) {
                    throw new LexicalException('Unterminated SQLite bracket identifier.');
                }
                $offset = $end + 1;
                $tokens[] = 'ID';
                continue;
            }
            if (preg_match('/\G[Xx]\'(?:[0-9A-Fa-f]{2})*\'/', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = 'BLOB';
                continue;
            }
            if (preg_match('/\G(?:\?[0-9]*|[:@$#][A-Za-z_][A-Za-z0-9_]*)/', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = 'VARIABLE';
                continue;
            }
            if (preg_match('/\G(?:(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?|\d+[eE][+-]?\d+)/', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = 'FLOAT';
                continue;
            }
            if (preg_match('/\G(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|\d(?:_?\d)*)/', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $tokens[] = str_contains($match[0], '_') ? 'QNUMBER' : 'INTEGER';
                continue;
            }
            if (preg_match('/\G[A-Za-z_][A-Za-z0-9_$]*/', $sql, $match, 0, $offset) === 1) {
                $offset += strlen($match[0]);
                $word = strtoupper($match[0]);
                $tokens[] = $this->keywordTokens[$word] ?? 'ID';
                continue;
            }

            $operator = $this->operatorAt($sql, $offset);
            if ($operator !== null) {
                $offset += strlen($operator[0]);
                $tokens[] = $operator[1];
                continue;
            }

            throw new LexicalException("Unsupported SQLite lexical input at offset {$offset}: {$sql}");
        }

        return $tokens;
    }

    /**
     * @return array{string, list<string>}
     */
    private function realizeTerminal(string $terminal): array
    {
        if (!$this->supports($terminal)) {
            throw new LexicalException("Unsupported SQLite terminal for {$this->profileVersion}: {$terminal}");
        }

        if (!$this->allowSyntheticTerminals) {
            $witnesses = $this->catalog->witnesses($terminal);
            $witness = $witnesses[$this->faker->numberBetween(0, count($witnesses) - 1)];

            return [$witness['sql'], $this->normalizeSourceTokens($witness['tokens'])];
        }

        return match ($terminal) {
            'ID', 'id', 'idj' => [$this->identifier(), ['ID']],
            'ids', 'STRING' => [$this->stringLiteral(), ['STRING']],
            'BLOB' => [$this->blobLiteral(), ['BLOB']],
            'number', 'INTEGER' => [$this->strings->integerString(0, PHP_INT_MAX), ['INTEGER']],
            'QNUMBER' => ['1_0', ['QNUMBER']],
            'VARIABLE' => [$this->parameter(), ['VARIABLE']],
            'ANY' => ['_any', ['ID']],
            'LP' => ['(', ['LP']],
            'RP' => [')', ['RP']],
            'SEMI' => [';', ['SEMI']],
            'COMMA' => [',', ['COMMA']],
            'DOT' => ['.', ['DOT']],
            'EQ' => ['=', ['EQ']],
            'LT' => ['<', ['LT']],
            'PLUS' => ['+', ['PLUS']],
            'MINUS' => ['-', ['MINUS']],
            'STAR' => ['*', ['STAR']],
            'BITAND' => ['&', ['BITAND']],
            'BITNOT' => ['~', ['BITNOT']],
            'CONCAT' => ['||', ['CONCAT']],
            'PTR' => ['->', ['PTR']],
            default => $this->fixedTerminal($terminal),
        };
    }

    /**
     * @return array{string, list<string>}
     */
    private function fixedTerminal(string $terminal): array
    {
        $lexemes = $this->keywords[$terminal] ?? null;
        $lexeme = $lexemes !== null
            ? $lexemes[$this->faker->numberBetween(0, count($lexemes) - 1)]
            : $terminal;

        return [$lexeme, $lexemes !== null ? [$terminal] : $this->tokenize($lexeme)];
    }

    private function identifier(): string
    {
        $body = $this->faker->numberBetween(0, 3) === 0 ? 'select' : '_' . $this->strings->rawIdentifier();

        return match ($this->faker->numberBetween(0, 7)) {
            0 => '"' . str_replace('"', '""', $body . '"quoted') . '"',
            1 => '`' . str_replace('`', '``', $body . '`quoted') . '`',
            2 => '[' . str_replace(']', '', $body) . ']',
            default => $body,
        };
    }

    private function stringLiteral(): string
    {
        $body = match ($this->faker->numberBetween(0, 5)) {
            0 => 'SELECT FROM WHERE',
            1 => '/* UPDATE */ -- DELETE',
            2 => "a'b",
            3 => 'a\\b',
            default => $this->strings->mixedAlnumString(0, 24),
        };

        return "'" . str_replace("'", "''", $body) . "'";
    }

    private function blobLiteral(): string
    {
        $length = $this->faker->numberBetween(0, 8) * 2;

        return "X'" . $this->strings->hexString($length, $length) . "'";
    }

    private function parameter(): string
    {
        return match ($this->faker->numberBetween(0, 4)) {
            0 => '?',
            1 => '?' . $this->faker->numberBetween(1, 10),
            2 => ':' . $this->strings->rawIdentifier(),
            3 => '@' . $this->strings->rawIdentifier(),
            default => '$' . $this->strings->rawIdentifier(),
        };
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
        if (substr($sql, $offset, 2) === '/*') {
            $end = strpos($sql, '*/', $offset + 2);
            if ($end === false) {
                throw new LexicalException('Unterminated SQLite block comment.');
            }
            $offset = $end + 2;

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

        throw new LexicalException("Unterminated SQLite quoted token: {$sql}");
    }

    /**
     * @return array{string, string}|null
     */
    private function operatorAt(string $sql, int $offset): ?array
    {
        foreach ([
            '->>' => 'PTR', '->' => 'PTR', '||' => 'CONCAT', '==' => 'EQ', '<=' => 'LE', '<>' => 'NE',
            '!=' => 'NE', '>=' => 'GE', '<<' => 'LSHIFT', '>>' => 'RSHIFT',
        ] as $operator => $token) {
            if (substr($sql, $offset, strlen($operator)) === $operator) {
                return [$operator, $token];
            }
        }

        $token = match ($sql[$offset]) {
            '(' => 'LP',
            ')' => 'RP',
            ';' => 'SEMI',
            ',' => 'COMMA',
            '.' => 'DOT',
            '=' => 'EQ',
            '<' => 'LT',
            '>' => 'GT',
            '+' => 'PLUS',
            '-' => 'MINUS',
            '*' => 'STAR',
            '/' => 'SLASH',
            '%' => 'REM',
            '&' => 'BITAND',
            '|' => 'BITOR',
            '~' => 'BITNOT',
            default => null,
        };

        return $token !== null ? [$sql[$offset], $token] : null;
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
            "SQLite lexical round-trip failed for %s.\nExpected: %s\nActual: %s\nSQL: %s",
            $this->profileVersion,
            json_encode($expected, JSON_THROW_ON_ERROR),
            json_encode($actual, JSON_THROW_ON_ERROR),
            $sql,
        );
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function normalizeSourceTokens(array $tokens): array
    {
        $normalized = [];
        foreach ($tokens as $token) {
            if ($token !== 'TK_SPACE') {
                $normalized[] = str_starts_with($token, 'TK_') ? substr($token, 3) : $token;
            }
        }

        return $normalized;
    }
}
