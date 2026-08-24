<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Faker\Generator as FakerGenerator;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\RandomStringGenerator;

/**
 * Writes one PostgreSQL parser terminal as the SQL text that produces it.
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
final class PgTerminalRealizer
{
    private const OPERATOR_CHARACTERS = '+-*/<>=~!@#%^&|`?';

    /** @readonly */
    private RandomStringGenerator $strings;

    /**
     * @param FakerGenerator $faker Source of the choices realization makes
     * @param LexicalCatalog $catalog Observed lexing examples for this server version
     * @param PgTokenizer $tokenizer Reads generated text back, to check synthetic spellings
     * @param PgLookahead $lookahead Substitutions the parser frontend makes
     * @param array<string, list<string>> $keywords Spellings by keyword terminal
     * @param string $profileVersion Lexical profile version in use
     * @param bool $allowSyntheticTerminals Whether terminals may be written without a witness
     * @param RandomStringGenerator|null $strings Produces the random parts of a lexeme
     */
    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly LexicalCatalog $catalog,
        private readonly PgTokenizer $tokenizer,
        private readonly PgLookahead $lookahead,
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
     * @return Realization The text, or null where the terminal writes nothing, and its tokens
     *
     * @throws LexicalException When the terminal or the requested text cannot be realized
     */
    public function realize(string $terminal, ?string $requestedLexeme = null): array
    {
        if (!$this->supports($terminal)) {
            throw LexicalException::unsupportedTerminal('PostgreSQL', $this->profileVersion, $terminal);
        }

        if ($requestedLexeme !== null) {
            return $this->realizeRequested($terminal, $requestedLexeme);
        }

        if (!$this->allowSyntheticTerminals) {
            return $this->realizeWitnessed($terminal);
        }

        if (str_starts_with($terminal, 'MODE_')) {
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
                throw LexicalException::lexemeDoesNotRealizeTerminal('PostgreSQL', $terminal, $requestedLexeme);
            }

            return [$requestedLexeme, $tokens];
        }

        foreach ($this->catalog->witnesses($terminal) as $witness) {
            if ($witness['sql'] === $requestedLexeme) {
                return [$requestedLexeme, $witness['tokens']];
            }
        }

        throw LexicalException::noWitnessForLexeme('PostgreSQL', $terminal, $requestedLexeme);
    }

    /**
     * Writes a terminal that stands for a fixed keyword.
     *
     * A token the parser frontend substitutes in has no spelling of its own, so
     * the keyword it replaced is looked up first.
     *
     * @param string $terminal Terminal to write
     *
     * @return array{string, list<string>} The spelling and its tokens
     *
     * @throws LexicalException When the spelling cannot be read back
     */
    public function realizeFixed(string $terminal): array
    {
        $spellings = $this->keywords[$this->lookahead->baseOf($terminal) ?? $terminal] ?? null;

        $lexeme = $spellings !== null
            ? $spellings[$this->faker->numberBetween(0, count($spellings) - 1)]
            : (str_ends_with($terminal, '_P') ? substr($terminal, 0, -2) : $terminal);

        return [$lexeme, $spellings !== null ? [$terminal] : $this->tokenizer->tokenize($lexeme)];
    }

    /**
     * Writes an identifier, quoted often enough to exercise both spellings.
     *
     * @return string An identifier
     */
    public function identifier(): string
    {
        if ($this->faker->numberBetween(0, 3) === 0) {
            return $this->quotedIdentifier(false);
        }

        return '_' . $this->strings->rawIdentifier();
    }

    /**
     * Writes a double-quoted identifier.
     *
     * @param bool $unicode Whether to write the `U&` prefix
     *
     * @return string A quoted identifier, sometimes holding a keyword or an escaped quote
     */
    public function quotedIdentifier(bool $unicode): string
    {
        $body = $this->faker->numberBetween(0, 3) === 0 ? 'values' : '_' . $this->strings->rawIdentifier();
        if ($this->faker->numberBetween(0, 7) === 0) {
            $body .= '"' . $this->strings->rawIdentifier();
        }

        return ($unicode ? 'U&' : '') . '"' . str_replace('"', '""', $body) . '"';
    }

    /**
     * Writes a string literal in one of the spellings PostgreSQL accepts.
     *
     * @return string A string literal
     */
    public function stringLiteral(): string
    {
        return match ($this->faker->numberBetween(0, 3)) {
            0 => "E'a\\\\b'",
            1 => $this->dollarQuotedString(),
            default => $this->standardStringLiteral(),
        };
    }

    /**
     * Writes a single-quoted string literal.
     *
     * @return string A string literal, sometimes holding a doubled quote
     */
    public function standardStringLiteral(): string
    {
        $body = match ($this->faker->numberBetween(0, 4)) {
            0, 1 => $this->strings->lexicalSequence($this->keywords),
            2 => "a'b",
            default => $this->strings->mixedAlnumString(0, 24),
        };

        return "'" . str_replace("'", "''", $body) . "'";
    }

    /**
     * Writes a dollar-quoted string literal, sometimes with a tag.
     *
     * @return string A dollar-quoted string
     */
    public function dollarQuotedString(): string
    {
        $tag = $this->faker->numberBetween(0, 1) === 0 ? '' : $this->strings->rawIdentifier(1, 8);
        $delimiter = '$' . $tag . '$';
        $body = $this->strings->lexicalSequence($this->keywords)
            . ' ? '
            . $this->strings->mixedAlnumString(0, 12);

        return $delimiter . $body . $delimiter;
    }

    /**
     * Writes a Unicode-escaped string literal.
     *
     * @return string A `U&`-prefixed string literal
     */
    public function unicodeStringLiteral(): string
    {
        return "U&'" . $this->strings->mixedAlnumString(0, 12) . "'";
    }

    /**
     * Writes a float literal in one of the shapes PostgreSQL accepts.
     *
     * @return string A float literal
     */
    public function decimalLiteral(): string
    {
        return match ($this->faker->numberBetween(0, 3)) {
            0 => '.5',
            1 => '1.',
            2 => '1e-1',
            default => $this->strings->decimalString(),
        };
    }

    /**
     * Writes a user-defined operator.
     *
     * A run of operator characters is only an operator if it is not something
     * else: `--` and the like open comments, and a run ending in `+` or `-`
     * without a character that makes it unambiguous would be read as arithmetic,
     * so its last character is replaced.
     *
     * @return string An operator
     */
    public function operator(): string
    {
        $common = ['?', '?|', '?&'];
        $commonIndex = $this->faker->numberBetween(0, 7);
        if (isset($common[$commonIndex])) {
            return $common[$commonIndex];
        }

        do {
            $operator = $this->randomOperator();
        } while (
            str_contains($operator, '--')
            || str_contains($operator, '/*')
            || $this->tokenizer->fixedOperator($operator) !== null
        );

        $last = strlen($operator) - 1;
        if (($operator[$last] === '+' || $operator[$last] === '-')
            && preg_match('/[~!@#%^&|`?]/', $operator) !== 1
        ) {
            $operator[$last] = '@';
        }

        return $operator;
    }

    /**
     * Writes a run of operator characters, with nothing said about its meaning.
     *
     * @return string A run of two to four operator characters
     */
    public function randomOperator(): string
    {
        $length = $this->faker->numberBetween(2, 4);
        $operator = '';
        for ($index = 0; $index < $length; ++$index) {
            $operator .= self::OPERATOR_CHARACTERS[
                $this->faker->numberBetween(0, strlen(self::OPERATOR_CHARACTERS) - 1)
            ];
        }

        return $operator;
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
