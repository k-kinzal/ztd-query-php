<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Lexical;

use Faker\Generator as FakerGenerator;
use InvalidArgumentException;
use Override;
use RuntimeException;
use SqlFaker\Grammar\Lexical\LexicalCatalog;
use SqlFaker\Grammar\Lexical\LexicalCatalogException;
use SqlFaker\Grammar\Lexical\LexicalException;
use SqlFaker\Grammar\Lexical\LexicalGrammar as LexicalGrammarContract;
use SqlFaker\Grammar\Lexical\LexicalKeywordIndex;
use SqlFaker\Grammar\Lexical\LexicalProfileSource;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\Source\TokenJoiner;
use SqlFaker\Grammar\Walk\GenerationPlan;

/**
 * SQLite lexical realization for one exact release.
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
    /**
     * The table option that makes a table reject values of the wrong type.
     */
    public const STRICT_TABLE_OPTION = SqliteTerminalRealizer::STRICT_TABLE_OPTION;

    /** @readonly */
    private RandomStringGenerator $strings;

    /** @readonly */
    private LexicalCatalog $catalog;

    /** @readonly */
    private SqliteTokenizer $tokenizer;

    /** @readonly */
    private SqliteTerminalRealizer $realizer;

    /**
     * @param FakerGenerator $faker Source of the choices realization makes
     * @param string $profileVersion Exact release to generate for, e.g. "sqlite-3.47.2"
     * @param bool $allowSyntheticTerminals Whether terminals may be written without a catalogued witness
     * @param LexicalProfileSource|null $profiles Loads the checked-in profile for the version
     * @param LexicalKeywordIndex|null $index Inverts the profile's terminal-to-spelling map
     *
     * @throws RuntimeException When the profile is missing or describes another release
     */
    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly string $profileVersion,
        private readonly bool $allowSyntheticTerminals = false,
        ?LexicalProfileSource $profiles = null,
        ?LexicalKeywordIndex $index = null,
    ) {
        /**
         * @var array{keywords: array<string, list<string>>, catalog: Catalog} $profile
         */
        $profile = ($profiles ?? new LexicalProfileSource())->load('sqlite', $profileVersion);
        $index ??= new LexicalKeywordIndex();

        $this->strings = new RandomStringGenerator($faker);
        $this->catalog = new LexicalCatalog($profile['catalog']);
        $this->tokenizer = new SqliteTokenizer($index->reversed($profile['keywords']));
        $this->realizer = new SqliteTerminalRealizer(
            $faker,
            $this->catalog,
            $this->tokenizer,
            $profile['keywords'],
            $profileVersion,
            $allowSyntheticTerminals,
            $this->strings,
        );
    }

    /**
     * Names the release this grammar generates for.
     *
     * @return string Profile version, e.g. "sqlite-3.47.2"
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
     * The strict-table option is spelled as an ordinary identifier, so the
     * catalog has no witness under that name and it is not asked about.
     *
     * @param list<string> $terminals Terminals the grammar declares
     *
     * @throws LexicalCatalogException When a terminal is neither witnessed nor excluded
     */
    public function assertTerminalsCovered(array $terminals): void
    {
        $this->catalog->assertTerminalsCovered(array_values(array_filter(
            $terminals,
            static fn (string $terminal): bool => $terminal !== self::STRICT_TABLE_OPTION,
        )));
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
            $lexemes[] = $lexeme;
            array_push($expected, ...$tokens);
        }

        $sql = TokenJoiner::join(
            $lexemes,
            [['->', '*'], ['*', '->']],
            fn (): string => $this->realizer->trivia(),
            fn (): string => $this->realizer->optionalTrivia(),
        );

        $actual = $this->tokenize($sql);
        if ($this->allowSyntheticTerminals) {
            $expected = $actual;
        }
        if ($actual !== $expected) {
            throw LexicalException::roundTripMismatch('SQLite', $this->profileVersion, $expected, $actual, $sql);
        }

        return $sql;
    }

    /**
     * Reads SQL text into the tokens SQLite's own lexer would produce.
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
    public function generateQuotedIdentifier(int $minLength = 1, int $maxLength = 128): string
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
    public function generateIntegerLiteral(int $min = 1, int $max = PHP_INT_MAX): string
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
    public function generateDecimalLiteral(int $precision = 15, int $scale = 2): string
    {
        return $this->strings->decimalString($precision, $scale);
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
            default => throw new InvalidArgumentException("Unknown SQLite lexical generation target: {$target}"),
        };
    }
}
