<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\RandomStringGenerator;

/**
 * Writes one MySQL parser terminal as the SQL text that produces it.
 *
 * There are two ways to write a terminal and they are not interchangeable. The
 * catalogued way replays SQL that the server's own lexer was observed to turn
 * into the terminal, which is what makes generated statements trustworthy. The
 * synthetic way constructs text from the terminal's name, which reaches
 * terminals no catalog witnesses but only promises to be plausible — so it is
 * off unless a caller asks for it.
 *
 * @phpstan-type Realization array{string|null, list<string>}
 */
final class MySqlTerminalRealizer
{
    /** @readonly */
    private RandomStringGenerator $strings;

    /**
     * @param FakerGenerator $faker Source of the choices realization makes
     * @param LexicalCatalog $catalog Observed lexing examples for this server version
     * @param MySqlTokenizer $tokenizer Reads generated text back, to check synthetic spellings
     * @param array<string, list<string>> $symbols Spellings by keyword or operator terminal
     * @param array<string, list<string>> $functions Spellings by function terminal
     * @param string $profileVersion Lexical profile version in use
     * @param bool $allowSyntheticTerminals Whether terminals may be written without a witness
     * @param RandomStringGenerator|null $strings Produces the random parts of a lexeme
     */
    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly LexicalCatalog $catalog,
        private readonly MySqlTokenizer $tokenizer,
        private readonly array $symbols,
        private readonly array $functions,
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
     * @return Realization The text, or null where the terminal writes nothing, and its tokens
     *
     * @throws LexicalException When the terminal or the requested text cannot be realized
     */
    public function realize(string $terminal, ?string $requestedLexeme = null): array
    {
        if (!$this->supports($terminal)) {
            throw LexicalException::unsupportedTerminal('MySQL', $this->profileVersion, $terminal);
        }

        if ($requestedLexeme !== null) {
            return $this->realizeRequested($terminal, $requestedLexeme);
        }

        if (!$this->allowSyntheticTerminals) {
            return $this->realizeWitnessed($terminal);
        }

        if (str_starts_with($terminal, 'GRAMMAR_SELECTOR_') || $terminal === 'END_OF_INPUT') {
            return [null, []];
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
        return $this->allowSyntheticTerminals || $this->catalog->supports($terminal);
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

        return [$witness['sql'] === '' ? null : $witness['sql'], $witness['tokens']];
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
                throw LexicalException::lexemeDoesNotRealizeTerminal('MySQL', $terminal, $requestedLexeme);
            }

            return [$requestedLexeme, $tokens];
        }

        foreach ($this->catalog->witnesses($terminal) as $witness) {
            if ($witness['sql'] === $requestedLexeme) {
                return [$requestedLexeme, $witness['tokens']];
            }
        }

        throw LexicalException::noWitnessForLexeme('MySQL', $terminal, $requestedLexeme);
    }

    /**
     * Writes a terminal that stands for a fixed keyword, operator or function name.
     *
     * @param string $terminal Terminal to write
     *
     * @return array{string, list<string>} The spelling and its tokens
     *
     * @throws LexicalException When the spelling cannot be read back
     */
    public function realizeFixed(string $terminal): array
    {
        if ($this->allowSyntheticTerminals) {
            $lexeme = $this->syntheticSpelling($terminal);

            return [$lexeme, $this->tokenizer->tokenize($lexeme)];
        }

        $spellings = $this->symbols[$terminal] ?? $this->functions[$terminal] ?? null;
        $lexeme = $spellings !== null
            ? $spellings[$this->faker->numberBetween(0, count($spellings) - 1)]
            : $terminal;

        return [
            $lexeme,
            isset($this->symbols[$terminal]) || isset($this->functions[$terminal])
                ? [$terminal]
                : $this->tokenizer->tokenize($lexeme),
        ];
    }

    /**
     * Guesses how a terminal is spelled from its name alone.
     *
     * Operator terminals are named rather than spelled, so those are listed;
     * everything else drops the `_SYM` suffix MySQL's grammar uses to keep
     * keyword terminals apart from the words they stand for.
     *
     * @param string $terminal Terminal to spell
     *
     * @return string The spelling
     */
    public function syntheticSpelling(string $terminal): string
    {
        return match ($terminal) {
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
    }

    /**
     * Writes a bare identifier.
     *
     * The leading underscore keeps the identifier from colliding with a keyword,
     * which would tokenize as that keyword instead.
     *
     * @return string An identifier
     */
    public function identifier(): string
    {
        return '_' . $this->strings->rawIdentifier();
    }

    /**
     * Writes a backtick-quoted identifier.
     *
     * @return string A quoted identifier, sometimes holding a keyword or an escaped backtick
     */
    public function quotedIdentifier(): string
    {
        $body = $this->faker->numberBetween(0, 3) === 0 ? 'select' : $this->identifier();
        if ($this->faker->numberBetween(0, 7) === 0) {
            $body .= '`' . $this->strings->rawIdentifier();
        }

        return '`' . str_replace('`', '``', $body) . '`';
    }

    /**
     * Writes a single-quoted string literal.
     *
     * @return string A string literal, sometimes holding a quote or a backslash
     */
    public function stringLiteral(): string
    {
        $body = match ($this->faker->numberBetween(0, 6)) {
            0, 1 => $this->strings->lexicalSequence($this->symbols + $this->functions),
            2 => "a'b",
            3 => 'a\\b',
            default => $this->strings->mixedAlnumString(0, 24),
        };

        return "'" . str_replace("'", "''", $body) . "'";
    }

    /**
     * Writes a dollar-quoted string literal.
     *
     * @return string A dollar-quoted string
     */
    public function dollarQuotedString(): string
    {
        return '$$' . $this->strings->mixedAlnumString(0, 24) . '$$';
    }

    /**
     * Writes a hexadecimal literal in one of its two spellings.
     *
     * @return string A hexadecimal literal
     */
    public function hexadecimalLiteral(): string
    {
        if ($this->faker->numberBetween(0, 1) === 0) {
            return '0x' . $this->strings->hexString();
        }

        $length = $this->faker->numberBetween(0, 8) * 2;

        return "X'" . $this->strings->hexString($length, $length) . "'";
    }

    /**
     * Writes a binary literal in one of its two spellings.
     *
     * @return string A binary literal
     */
    public function binaryLiteral(): string
    {
        if ($this->faker->numberBetween(0, 1) === 0) {
            return '0b' . $this->strings->binaryString();
        }

        return "B'" . $this->strings->binaryString(0, 32) . "'";
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
