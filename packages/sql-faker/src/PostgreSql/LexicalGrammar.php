<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Faker\Generator as FakerGenerator;
use InvalidArgumentException;
use Override;
use RuntimeException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalCatalogException;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar as LexicalGrammarContract;
use SqlFaker\Grammar\LexicalKeywordIndex;
use SqlFaker\Grammar\LexicalProfileSource;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\TokenJoiner;

/**
 * PostgreSQL lexical realization for one exact server version.
 *
 * Writing a terminal sequence as SQL is only half of what this does. The text
 * is read straight back by the dialect's own tokenizer and the two token
 * sequences are compared, so a generator that believed it was writing one
 * statement and a server that would have read another is caught here rather
 * than by the server. That round trip is the contract; realizing, tokenizing
 * and the parser frontend's lookahead are the collaborators it holds.
 */
final class LexicalGrammar implements LexicalGrammarContract
{
    /** @readonly */
    private RandomStringGenerator $strings;

    /** @readonly */
    private LexicalCatalog $catalog;

    /** @readonly */
    private PgLookahead $lookahead;

    /** @readonly */
    private PgTokenizer $tokenizer;

    /** @readonly */
    private PgTerminalRealizer $realizer;

    /**
     * @param FakerGenerator $faker Source of the choices realization makes
     * @param string $profileVersion Exact server version to generate for, e.g. "pg-17.2"
     * @param bool $allowSyntheticTerminals Whether terminals may be written without a catalogued witness
     * @param LexicalProfileSource|null $profiles Loads the checked-in profile for the version
     * @param LexicalKeywordIndex|null $index Inverts the profile's terminal-to-spelling map
     *
     * @throws RuntimeException When the profile is missing or describes another server
     */
    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly string $profileVersion,
        private readonly bool $allowSyntheticTerminals = false,
        ?LexicalProfileSource $profiles = null,
        ?LexicalKeywordIndex $index = null,
    ) {
        /**
         * @var array{keywords: array<string, list<string>>, lookahead: array<string, array{token: string, followed_by: list<string>}>, catalog: array<string, mixed>} $profile
         */
        $profile = ($profiles ?? new LexicalProfileSource())->load('postgresql', $profileVersion);
        $index ??= new LexicalKeywordIndex();

        $this->strings = new RandomStringGenerator($faker);
        $this->catalog = new LexicalCatalog($profile['catalog']);
        $this->lookahead = new PgLookahead($profile['lookahead']);
        $this->tokenizer = new PgTokenizer($index->reversed($profile['keywords']), $this->lookahead);
        $this->realizer = new PgTerminalRealizer(
            $faker,
            $this->catalog,
            $this->tokenizer,
            $this->lookahead,
            $profile['keywords'],
            $profileVersion,
            $allowSyntheticTerminals,
            $this->strings,
        );
    }

    /**
     * Names the server version this grammar generates for.
     *
     * @return string Profile version, e.g. "pg-17.2"
     */
    #[Override]
    public function version(): string
    {
        return $this->profileVersion;
    }

    /**
     * Reports whether a parser terminal can be written as SQL.
     *
     * @param string $terminal Terminal to look for
     *
     * @return bool True when the terminal can be realized
     */
    #[Override]
    public function supports(string $terminal): bool
    {
        return $this->realizer->supports($terminal);
    }

    /**
     * Checks that every terminal a grammar declares can be accounted for.
     *
     * @param list<string> $terminals Terminals the grammar declares
     *
     * @throws LexicalCatalogException When a terminal is neither witnessed nor excluded
     */
    public function assertTerminalsCovered(array $terminals): void
    {
        $this->catalog->assertTerminalsCovered($terminals);
    }

    /**
     * Settles each terminal on the spelling its neighbour calls for.
     *
     * @param list<string> $terminals Terminals a derivation produced
     *
     * @return list<string> The terminals with each lookahead substitution settled
     */
    public function normalizeLookahead(array $terminals): array
    {
        return $this->lookahead->normalized($terminals);
    }

    /**
     * Writes a terminal sequence as SQL and checks that it reads back as itself.
     *
     * @param list<string> $terminals Terminals to write, in order
     * @param GenerationPlan<bool>|null $plan Plan that may pin exact lexemes for some terminals
     *
     * @return string SQL that tokenizes back to the terminals it was written from
     *
     * @throws LexicalException When a terminal cannot be written, or the text does not read back
     */
    #[Override]
    public function realize(array $terminals, ?GenerationPlan $plan = null): string
    {
        $lexemes = [];
        $expected = [];
        /** @var array<string, int> $occurrences */
        $occurrences = [];

        foreach ($terminals as $terminal) {
            $occurrence = $occurrences[$terminal] ?? 0;
            $occurrences[$terminal] = $occurrence + 1;

            [$lexeme, $tokens] = $this->realizer->realize($terminal, $plan?->lexemeAt($terminal, $occurrence));
            if ($lexeme !== null) {
                $lexemes[] = $lexeme;
            }
            array_push($expected, ...$tokens);
        }

        $sql = TokenJoiner::join(
            $lexemes,
            [['::', '*'], ['*', '::']],
            fn (): string => $this->realizer->trivia(),
            fn (): string => $this->realizer->optionalTrivia(),
        );

        $actual = $this->tokenize($sql);
        if ($this->allowSyntheticTerminals) {
            $expected = $actual;
        }
        if ($actual !== $expected) {
            throw LexicalException::roundTripMismatch('PostgreSQL', $this->profileVersion, $expected, $actual, $sql);
        }

        return $sql;
    }

    /**
     * Reads SQL text into the tokens PostgreSQL's own lexer would produce.
     *
     * @param string $sql Text to read
     *
     * @return list<string> Parser token names, in order
     *
     * @throws LexicalException When the text holds something the lexer cannot read
     */
    public function tokenize(string $sql): array
    {
        return $this->tokenizer->tokenize($sql);
    }

    /**
     * Writes a double-quoted identifier of a bounded length.
     *
     * @param int $minLength Shortest identifier body to write
     * @param int $maxLength Longest identifier body to write
     *
     * @return non-empty-string A quoted identifier
     */
    public function generateQuotedIdentifier(int $minLength = 1, int $maxLength = 63): string
    {
        return '"' . $this->strings->rawIdentifier($minLength, $maxLength) . '"';
    }

    /**
     * Writes a single-quoted string literal of a bounded length.
     *
     * @param int $minLength Shortest body to write
     * @param int $maxLength Longest body to write
     *
     * @return non-empty-string A string literal
     */
    public function generateStringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return "'" . $this->strings->mixedAlnumString($minLength, $maxLength) . "'";
    }

    /**
     * Writes an integer literal.
     *
     * @param int $min Smallest value to write
     * @param int $max Largest value to write
     *
     * @return non-empty-string An integer literal
     */
    public function generateIntegerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->strings->integerString($min, $max);
    }

    /**
     * Writes a fixed-point literal.
     *
     * @param int $precision Total digits to write
     * @param int $scale Digits after the point
     *
     * @return non-empty-string A decimal literal
     */
    public function generateDecimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->strings->decimalString($precision, $scale);
    }

    /**
     * Writes a floating-point literal in exponent form.
     *
     * @param int $precision Total digits of the mantissa
     * @param int $scale Digits after the point in the mantissa
     * @param int $minExponent Smallest exponent to write
     * @param int $maxExponent Largest exponent to write
     *
     * @return non-empty-string A float literal
     */
    public function generateFloatLiteral(
        int $precision = 10,
        int $scale = 2,
        int $minExponent = -307,
        int $maxExponent = 308,
    ): string {
        return $this->strings->floatString(
            $this->generateDecimalLiteral($precision, $scale),
            $minExponent,
            $maxExponent,
        );
    }

    /**
     * Writes a hexadecimal bit-string literal.
     *
     * @param int $minLength Fewest hex digits to write
     * @param int $maxLength Most hex digits to write
     *
     * @return non-empty-string A hexadecimal literal
     */
    public function generateHexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return "X'" . $this->strings->hexString($minLength, $maxLength) . "'";
    }

    /**
     * Writes a binary bit-string literal.
     *
     * @param int $minLength Fewest bits to write
     * @param int $maxLength Most bits to write
     *
     * @return non-empty-string A binary literal
     */
    public function generateBinaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return "B'" . $this->strings->binaryString($minLength, $maxLength) . "'";
    }

    /**
     * Writes a dollar-quoted string literal.
     *
     * @param int $minLength Shortest body to write
     * @param int $maxLength Longest body to write
     *
     * @return non-empty-string A dollar-quoted string
     */
    public function generateDollarQuotedString(int $minLength = 1, int $maxLength = 255): string
    {
        return '$$' . $this->strings->mixedAlnumString($minLength, $maxLength) . '$$';
    }

    /**
     * Writes a positional parameter marker.
     *
     * @param int $min Smallest position to write
     * @param int $max Largest position to write
     *
     * @return non-empty-string A parameter marker
     */
    public function generateParameterMarker(int $min = 1, int $max = 99): string
    {
        return '$' . $this->strings->parameterIndex($min, $max);
    }

    /**
     * Writes the one lexeme a lexical generation plan asks for.
     *
     * @param GenerationPlan<bool> $plan Plan naming the lexeme kind and its bounds
     *
     * @return non-empty-string The lexeme
     *
     * @throws InvalidArgumentException When the plan names a lexeme kind this dialect has none of
     */
    #[Override]
    public function generate(GenerationPlan $plan): string
    {
        $target = $plan->lexicalTarget();
        $parameters = $plan->parameters();

        return match ($target) {
            'quoted_identifier' => $this->generateQuotedIdentifier($parameters['minLength'], $parameters['maxLength']),
            'string_literal' => $this->generateStringLiteral($parameters['minLength'], $parameters['maxLength']),
            'integer_literal' => $this->generateIntegerLiteral($parameters['min'], $parameters['max']),
            'decimal_literal' => $this->generateDecimalLiteral($parameters['precision'], $parameters['scale']),
            'float_literal' => $this->generateFloatLiteral(
                $parameters['precision'],
                $parameters['scale'],
                $parameters['minExponent'],
                $parameters['maxExponent'],
            ),
            'hex_literal' => $this->generateHexLiteral($parameters['minLength'], $parameters['maxLength']),
            'binary_literal' => $this->generateBinaryLiteral($parameters['minLength'], $parameters['maxLength']),
            'dollar_quoted_string' => $this->generateDollarQuotedString(
                $parameters['minLength'],
                $parameters['maxLength'],
            ),
            'parameter_marker' => $this->generateParameterMarker($parameters['min'], $parameters['max']),
            default => throw new InvalidArgumentException("Unknown PostgreSQL lexical generation target: {$target}"),
        };
    }
}
