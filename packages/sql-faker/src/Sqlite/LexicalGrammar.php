<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use InvalidArgumentException;
use Override;
use RuntimeException;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Lexeme\LexicalGrammar as LexicalGrammarContract;
use SqlFaker\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Generation\Output\SqlSerializer;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Sqlite\Generation\Lexeme\DefinitionFactory;
use SqlFaker\Sqlite\Generation\Value\LiteralGenerator;
use SqlFaker\Sqlite\Tokenization\KeywordIndex;
use SqlFaker\Sqlite\Tokenization\SqliteTokenizer;

/**
 * SQLite lexical generation using source-based candidate and boundary definitions.
 * Tokenization is exposed separately for diagnostics and does not decide generation success.
 *
 * @visibility root
 */
final class LexicalGrammar implements LexicalGrammarContract
{
    /**
     * The table option that makes a table reject values of the wrong type.
     */
    public const STRICT_TABLE_OPTION = 'STRICT_TABLE_OPTION';

    /**
     * @readonly
     */
    private LiteralGenerator $strings;

    private readonly ReverseLexemeGenerator $pipeline;

    /**
     * @readonly
     */
    private SqliteTokenizer $tokenizer;

    /**
     * @param FakerGenerator $faker Source of the choices realization makes
     * @param string $profileVersion Exact release to generate for, e.g. "sqlite-3.47.2"
     * @param KeywordIndex|null $index Inverts the profile's terminal-to-spelling map
     *
     * @throws RuntimeException When the exact release has no declaration
     */
    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly string $profileVersion,
        ?KeywordIndex $index = null,
    ) {
        $definition = (new DefinitionFactory())->create($profileVersion);
        $index ??= new KeywordIndex();
        $this->strings = new LiteralGenerator($faker);
        $this->pipeline = $definition->pipeline;
        $this->tokenizer = new SqliteTokenizer($index->reversed($definition->keywords));
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
     * SQLite's grammar terminals all emit text; its implicit EOF is outside the terminal sequence.
     */
    #[Override]
    public function isNonOutput(string $terminal): bool
    {
        return false;
    }

    /**
     * Realizes a terminal sequence using the same candidate and spacing pipeline as grammar generation.
     * @param list<string> $terminals
     * @param GenerationPlan<bool>|null $plan
     * @throws LexicalException When a requested realization is unavailable
     */
    #[Override]
    public function realize(array $terminals, ?GenerationPlan $plan = null): string
    {
        return $this->realizeSequence(TerminalSequence::fromNames($terminals), $plan);
    }

    /**
     * Resolves candidates and boundaries before serialization; tokenization remains a diagnostic API.
     * @param GenerationPlan<bool>|null $plan
     * @throws LexicalException When the selected terminals have no applicable realization
     */
    public function realizeSequence(TerminalSequence $sequence, ?GenerationPlan $plan = null): string
    {
        $output = $this->pipeline->generate($sequence, $plan, fn (int $count): int => $this->faker->numberBetween(0, $count - 1));
        return (new SqlSerializer())->serialize($output->pieces());
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
