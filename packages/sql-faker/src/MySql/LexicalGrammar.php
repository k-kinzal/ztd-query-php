<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use InvalidArgumentException;
use Override;
use RuntimeException;
use SqlFaker\Grammar\Lexical\LexicalCatalog;
use SqlFaker\Grammar\Lexical\LexicalException;
use SqlFaker\Grammar\Lexical\LexicalGrammar as LexicalGrammarContract;
use SqlFaker\Grammar\Lexical\LexicalKeywordIndex;
use SqlFaker\Grammar\Lexical\LexicalProfileSource;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\Source\TokenJoiner;
use SqlFaker\Grammar\Walk\GenerationPlan;

/**
 * MySQL lexical realization for one exact server version and the default sql_mode.
 *
 * Writing a terminal sequence as SQL is only half of what this does. The text
 * is read straight back by the dialect's own tokenizer and the two token
 * sequences are compared, so a generator that believed it was writing one
 * statement and a server that would have read another is caught here rather
 * than by the server. That round trip is the contract; realizing and tokenizing
 * are the two collaborators it holds.
 * @phpstan-import-type Catalog from LexicalCatalog
 */
final class LexicalGrammar implements LexicalGrammarContract
{
    /** @readonly */
    private RandomStringGenerator $strings;

    /** @readonly */
    private LexicalCatalog $catalog;

    /** @readonly */
    private MySqlTokenizer $tokenizer;

    /** @readonly */
    private MySqlTerminalRealizer $realizer;

    /**
     * @param FakerGenerator $faker Source of the choices realization makes
     * @param string $profileVersion Exact server version to generate for, e.g. "mysql-8.4.7"
     * @param bool $allowSyntheticTerminals Whether terminals may be written without a catalogued witness
     * @param LexicalProfileSource|null $profiles Loads the checked-in profile for the version
     * @param LexicalKeywordIndex|null $index Inverts the profile's terminal-to-spelling maps
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
         * @var array{symbols: array<string, list<string>>, functions: array<string, list<string>>, features: array{dollar_quoted_strings: bool}, catalog: Catalog} $profile
         */
        $profile = ($profiles ?? new LexicalProfileSource())->load('mysql', $profileVersion);
        $index ??= new LexicalKeywordIndex();

        $this->strings = new RandomStringGenerator($faker);
        $this->catalog = new LexicalCatalog($profile['catalog']);
        $this->tokenizer = new MySqlTokenizer(
            $index->reversed($profile['symbols']),
            $index->reversed($profile['functions']),
            $profile['features']['dollar_quoted_strings'],
        );
        $this->realizer = new MySqlTerminalRealizer(
            $faker,
            $this->catalog,
            $this->tokenizer,
            $profile['symbols'],
            $profile['functions'],
            $profileVersion,
            $allowSyntheticTerminals,
            $this->strings,
        );
    }

    /**
     * Names the server version this grammar generates for.
     *
     * @return string Profile version, e.g. "mysql-8.4.7"
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
     * @throws \SqlFaker\Grammar\Lexical\LexicalCatalogException When a terminal is neither witnessed nor excluded
     */
    public function assertTerminalsCovered(array $terminals): void
    {
        $this->catalog->assertTerminalsCovered($terminals);
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
            [['@', '*'], ['*', '@']],
            fn (): string => $this->realizer->trivia(),
            fn (): string => $this->realizer->optionalTrivia(),
        );

        $actual = $this->tokenize($sql);
        if ($this->allowSyntheticTerminals) {
            $expected = $actual;
        }
        if ($actual !== $expected) {
            throw LexicalException::roundTripMismatch('MySQL', $this->profileVersion, $expected, $actual, $sql);
        }

        return $sql;
    }

    /**
     * Reads SQL text into the tokens MySQL's own lexer would produce.
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
     * Writes a backtick-quoted identifier of a bounded length.
     *
     * @param int $minLength Shortest identifier body to write
     * @param int $maxLength Longest identifier body to write
     *
     * @return non-empty-string A quoted identifier
     */
    public function generateQuotedIdentifier(int $minLength = 1, int $maxLength = 64): string
    {
        return '`' . $this->strings->rawIdentifier($minLength, $maxLength) . '`';
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
     * Writes a national character string literal.
     *
     * @param int $minLength Shortest body to write
     * @param int $maxLength Longest body to write
     *
     * @return non-empty-string An N-prefixed string literal
     */
    public function generateNationalStringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return 'N' . $this->generateStringLiteral($minLength, $maxLength);
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
     * Writes an integer literal inside the range MySQL reads as NUM.
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
     * Writes an integer literal wide enough for MySQL to read as LONG_NUM.
     *
     * @param int $min Smallest value to write
     * @param int $max Largest value to write
     *
     * @return non-empty-string An integer literal
     */
    public function generateLongIntegerLiteral(int $min = 0, int $max = 2147483647): string
    {
        return $this->strings->longIntString($min, $max);
    }

    /**
     * Writes an integer literal wide enough for MySQL to read as ULONGLONG_NUM.
     *
     * @param int $minLength Fewest digits to write
     * @param int $maxLength Most digits to write
     *
     * @return non-empty-string An integer literal
     */
    public function generateUnsignedBigIntLiteral(int $minLength = 1, int $maxLength = 20): string
    {
        return $this->strings->unsignedBigIntString($minLength, $maxLength);
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
        int $minExponent = -38,
        int $maxExponent = 38,
    ): string {
        return $this->strings->floatString(
            $this->generateDecimalLiteral($precision, $scale),
            $minExponent,
            $maxExponent,
        );
    }

    /**
     * Writes a hexadecimal literal in `0x` form.
     *
     * @param int $minLength Fewest hex digits to write
     * @param int $maxLength Most hex digits to write
     *
     * @return non-empty-string A hexadecimal literal
     */
    public function generateHexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return '0x' . $this->strings->hexString($minLength, $maxLength);
    }

    /**
     * Writes a hexadecimal literal in `X'..'` form, which takes whole bytes.
     *
     * @param int $minBytes Fewest bytes to write
     * @param int $maxBytes Most bytes to write
     *
     * @return non-empty-string A quoted hexadecimal literal
     */
    public function generateQuotedHexLiteral(int $minBytes = 1, int $maxBytes = 8): string
    {
        $bytes = $this->faker->numberBetween($minBytes, $maxBytes);

        return "X'" . $this->strings->hexString($bytes * 2, $bytes * 2) . "'";
    }

    /**
     * Writes a binary literal in `0b` form.
     *
     * @param int $minLength Fewest bits to write
     * @param int $maxLength Most bits to write
     *
     * @return non-empty-string A binary literal
     */
    public function generateBinaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return '0b' . $this->strings->binaryString($minLength, $maxLength);
    }

    /**
     * Writes a hostname, as it appears after the `@` of a user specification.
     *
     * @param int $minParts Fewest dot-separated parts to write
     * @param int $maxParts Most dot-separated parts to write
     * @param int $maxPartLength Longest single part to write
     *
     * @return non-empty-string A hostname
     */
    public function generateHostname(int $minParts = 1, int $maxParts = 4, int $maxPartLength = 63): string
    {
        return $this->strings->hostnameString($minParts, $maxParts, 1, $maxPartLength);
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
            'national_string_literal' => $this->generateNationalStringLiteral(
                $parameters['minLength'],
                $parameters['maxLength'],
            ),
            'dollar_quoted_string' => $this->generateDollarQuotedString(
                $parameters['minLength'],
                $parameters['maxLength'],
            ),
            'integer_literal' => $this->generateIntegerLiteral($parameters['min'], $parameters['max']),
            'long_integer_literal' => $this->generateLongIntegerLiteral($parameters['min'], $parameters['max']),
            'unsigned_big_int_literal' => $this->generateUnsignedBigIntLiteral(
                $parameters['minLength'],
                $parameters['maxLength'],
            ),
            'decimal_literal' => $this->generateDecimalLiteral($parameters['precision'], $parameters['scale']),
            'float_literal' => $this->generateFloatLiteral(
                $parameters['precision'],
                $parameters['scale'],
                $parameters['minExponent'],
                $parameters['maxExponent'],
            ),
            'hex_literal' => $this->generateHexLiteral($parameters['minLength'], $parameters['maxLength']),
            'quoted_hex_literal' => $this->generateQuotedHexLiteral($parameters['minBytes'], $parameters['maxBytes']),
            'binary_literal' => $this->generateBinaryLiteral($parameters['minLength'], $parameters['maxLength']),
            'hostname' => $this->generateHostname(
                $parameters['minParts'],
                $parameters['maxParts'],
                $parameters['maxPartLength'],
            ),
            default => throw new InvalidArgumentException("Unknown MySQL lexical generation target: {$target}"),
        };
    }
}
