<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;

/**
 * Writes one SQLite parser terminal as the SQL text that produces it.
 *
 * There are two ways to write a terminal and they are not interchangeable. The
 * catalogued way replays SQL that SQLite's own lexer was observed to turn into
 * the terminal, which is what makes generated statements trustworthy. The
 * synthetic way constructs text from the terminal's name, which reaches
 * terminals no catalog witnesses but only promises to be plausible — so it is
 * off unless a caller asks for it.
 *
 * @phpstan-type Realization array{string, list<string>}
 */
final class SqliteTerminalRealizer
{
    /**
     * The table option that makes a table reject values of the wrong type.
     *
     * SQLite's grammar spells this as an ordinary identifier, so the generator
     * needs a terminal of its own to ask for it deliberately rather than hoping
     * an identifier happens to come out as the word STRICT.
     */
    public const STRICT_TABLE_OPTION = 'STRICT_TABLE_OPTION';

    private const OPERATOR_TERMINALS = [
        'LP' => '(', 'RP' => ')', 'SEMI' => ';', 'COMMA' => ',', 'DOT' => '.',
        'EQ' => '=', 'LT' => '<', 'LE' => '<=', 'GT' => '>', 'GE' => '>=', 'NE' => '<>',
        'PLUS' => '+', 'MINUS' => '-', 'STAR' => '*', 'SLASH' => '/', 'REM' => '%',
        'BITAND' => '&', 'BITOR' => '|', 'BITNOT' => '~', 'LSHIFT' => '<<', 'RSHIFT' => '>>',
        'CONCAT' => '||', 'PTR' => '->',
    ];

    /** @readonly */
    private RandomStringGenerator $strings;

    /**
     * @param FakerGenerator $faker Source of the choices realization makes
     * @param LexicalCatalog $catalog Observed lexing examples for this release
     * @param SqliteTokenizer $tokenizer Reads generated text back, to check synthetic spellings
     * @param array<string, list<string>> $keywords Spellings by keyword terminal
     * @param string $profileVersion Lexical profile version in use
     * @param bool $allowSyntheticTerminals Whether terminals may be written without a witness
     * @param RandomStringGenerator|null $strings Produces the random parts of a lexeme
     */
    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly LexicalCatalog $catalog,
        private readonly SqliteTokenizer $tokenizer,
        private readonly array $keywords,
        private readonly string $profileVersion,
        private readonly bool $allowSyntheticTerminals,
        ?RandomStringGenerator $strings = null,
    ) {
        $this->strings = $strings ?? new RandomStringGenerator($faker);
    }

    /**
     * Writes one terminal, reporting the tokens the text should read back as.
     *
     * @param string $terminal Terminal to write
     * @param non-empty-string|null $requestedLexeme Exact text the caller wants, when it has one
     *
     * @return Realization The text and the tokens it should read back as
     *
     * @throws LexicalException When the terminal or the requested text cannot be realized
     */
    public function realize(string $terminal, ?string $requestedLexeme = null): array
    {
        if (!$this->supports($terminal)) {
            throw LexicalException::unsupportedTerminal('SQLite', $this->profileVersion, $terminal);
        }

        if ($terminal === self::STRICT_TABLE_OPTION) {
            return ['STRICT', ['ID']];
        }

        if ($requestedLexeme !== null) {
            return $this->realizeRequested($terminal, $requestedLexeme);
        }

        if (!$this->allowSyntheticTerminals) {
            return $this->realizeWitnessed($terminal);
        }

        return $this->realizeSynthetic($terminal);
    }

    /**
     * Reports whether this realizer can write a terminal at all.
     *
     * @param string $terminal Terminal to look for
     *
     * @return bool True when the catalog witnesses it, or synthetic writing is allowed
     */
    public function supports(string $terminal): bool
    {
        return $terminal === self::STRICT_TABLE_OPTION
            || $this->allowSyntheticTerminals
            || $this->catalog->supports($terminal);
    }

    /**
     * Writes a terminal by replaying one of its catalogued examples.
     *
     * @param string $terminal Terminal to write
     *
     * @return Realization The example's text and the tokens it was observed to produce
     */
    public function realizeWitnessed(string $terminal): array
    {
        $witnesses = $this->catalog->witnesses($terminal);
        $witness = $witnesses[$this->faker->numberBetween(0, count($witnesses) - 1)];

        return [$witness['sql'], $this->tokenizer->normalizedSourceTokens($witness['tokens'])];
    }

    /**
     * Writes a terminal from its name rather than from an observed example.
     *
     * @param string $terminal Terminal to write
     *
     * @return Realization The constructed text and its tokens
     *
     * @throws LexicalException When the constructed text cannot be read back
     */
    public function realizeSynthetic(string $terminal): array
    {
        $operator = self::OPERATOR_TERMINALS[$terminal] ?? null;
        if ($operator !== null) {
            return [$operator, [$terminal]];
        }

        return match ($terminal) {
            'ID', 'id', 'idj' => [$this->identifier(), ['ID']],
            'ids', 'STRING' => [$this->stringLiteral(), ['STRING']],
            'BLOB' => [$this->blobLiteral(), ['BLOB']],
            'number', 'INTEGER' => [$this->strings->integerString(0, PHP_INT_MAX), ['INTEGER']],
            'FLOAT' => [$this->strings->decimalString(), ['FLOAT']],
            'QNUMBER' => ['1_0', ['QNUMBER']],
            'VARIABLE' => [$this->parameter(), ['VARIABLE']],
            'ANY' => ['_any', ['ID']],
            default => $this->realizeFixed($terminal),
        };
    }

    /**
     * Writes the exact text a caller asked for, once it is shown to realize the terminal.
     *
     * @param string $terminal Terminal the text is meant to realize
     * @param non-empty-string $requestedLexeme Exact text the caller wants
     *
     * @return array{non-empty-string, list<string>} The text and its tokens
     *
     * @throws LexicalException When the text does not realize the terminal
     */
    public function realizeRequested(string $terminal, string $requestedLexeme): array
    {
        if ($this->allowSyntheticTerminals) {
            $tokens = $this->tokenizer->tokenize($requestedLexeme);
            if ($tokens !== [$terminal]) {
                throw LexicalException::lexemeDoesNotRealizeTerminal('SQLite', $terminal, $requestedLexeme);
            }

            return [$requestedLexeme, $tokens];
        }

        foreach ($this->catalog->witnesses($terminal) as $witness) {
            if ($witness['sql'] === $requestedLexeme) {
                return [$requestedLexeme, $this->tokenizer->normalizedSourceTokens($witness['tokens'])];
            }
        }

        throw LexicalException::noWitnessForLexeme('SQLite', $terminal, $requestedLexeme);
    }

    /**
     * Writes a terminal that stands for a fixed keyword.
     *
     * @param string $terminal Terminal to write
     *
     * @return Realization The spelling and its tokens
     *
     * @throws LexicalException When the spelling cannot be read back
     */
    public function realizeFixed(string $terminal): array
    {
        $spellings = $this->keywords[$terminal] ?? null;
        $lexeme = $spellings !== null
            ? $spellings[$this->faker->numberBetween(0, count($spellings) - 1)]
            : $terminal;

        return [$lexeme, $spellings !== null ? [$terminal] : $this->tokenizer->tokenize($lexeme)];
    }

    /**
     * Writes an identifier, quoted often enough to exercise all four spellings.
     *
     * @return string An identifier
     */
    public function identifier(): string
    {
        $body = $this->faker->numberBetween(0, 3) === 0 ? 'select' : '_' . $this->strings->rawIdentifier();

        return match ($this->faker->numberBetween(0, 7)) {
            0 => SqliteQuoting::identifier($body . '"quoted'),
            1 => SqliteQuoting::backtickIdentifier($body . '`quoted'),
            2 => SqliteQuoting::bracketIdentifier($body . ']quoted'),
            default => $body,
        };
    }

    /**
     * Writes a single-quoted string literal.
     *
     * @return string A string literal, sometimes holding a quote or a backslash
     */
    public function stringLiteral(): string
    {
        $body = match ($this->faker->numberBetween(0, 5)) {
            0, 1 => $this->strings->lexicalSequence($this->keywords),
            2 => "a'b",
            3 => 'a\\b',
            default => $this->strings->mixedAlnumString(0, 24),
        };

        return SqliteQuoting::stringLiteral($body);
    }

    /**
     * Writes a blob literal, which takes whole bytes.
     *
     * @return string A blob literal
     */
    public function blobLiteral(): string
    {
        $length = $this->faker->numberBetween(0, 8) * 2;

        return "X'" . $this->strings->hexString($length, $length) . "'";
    }

    /**
     * Writes a bound parameter in one of the five spellings SQLite accepts.
     *
     * @return string A parameter
     */
    public function parameter(): string
    {
        return match ($this->faker->numberBetween(0, 4)) {
            0 => '?',
            1 => '?' . $this->faker->numberBetween(1, 10),
            2 => ':' . $this->strings->rawIdentifier(),
            3 => '@' . $this->strings->rawIdentifier(),
            default => '$' . $this->strings->rawIdentifier(),
        };
    }

    /**
     * Writes the separator that has to appear between two lexemes.
     *
     * @return string Whitespace or a comment
     */
    public function trivia(): string
    {
        if ($this->allowSyntheticTerminals) {
            return ' ';
        }

        $witnesses = $this->catalog->witnesses('@TRIVIA');

        return $witnesses[$this->faker->numberBetween(0, count($witnesses) - 1)]['sql'];
    }

    /**
     * Writes the separator that may appear between two lexemes.
     *
     * @return string Whitespace, a comment, or nothing
     */
    public function optionalTrivia(): string
    {
        if ($this->allowSyntheticTerminals || $this->faker->numberBetween(0, 1) === 0) {
            return '';
        }

        return $this->trivia();
    }
}
