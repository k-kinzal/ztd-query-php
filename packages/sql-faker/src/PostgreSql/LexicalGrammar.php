<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Closure;
use Faker\Generator as FakerGenerator;
use InvalidArgumentException;
use Override;
use RuntimeException;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\SqlSerializer;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar as LexicalGrammarContract;
use SqlFaker\PostgreSql\Generation\Lexeme\DefinitionFactory;
use SqlFaker\PostgreSql\Generation\Value\LiteralGenerator;
use SqlFaker\PostgreSql\Tokenization\KeywordIndex;

/**
 * PostgreSQL lexical generation using source-based candidate and boundary definitions.
 * Tokenization is exposed separately for diagnostics and does not decide generation success.
 *
 * @visibility root
 */
final class LexicalGrammar implements LexicalGrammarContract
{
    /**
     * @readonly
     */
    private LiteralGenerator $strings;

    private readonly ReverseLexemeGenerator $pipeline;

    /**
     * @var list<string>
     */
    private readonly array $nonOutput;

    /**
     * @readonly
     */
    private PgLookahead $lookahead;

    /**
     * @readonly
     */
    private PgTokenizer $tokenizer;

    /**
     * @param FakerGenerator $faker Source of the choices realization makes
     * @param string $profileVersion Exact server version to generate for, e.g. "pg-17.2"
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
        $this->nonOutput = $definition->nonOutput;
        $this->lookahead = new PgLookahead(PgLookahead::definitions());
        $this->tokenizer = new PgTokenizer($index->reversed($definition->keywords), $this->lookahead);
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
     * Reports parser selector tokens that intentionally write no characters.
     */
    #[Override]
    public function isNonOutput(string $terminal): bool
    {
        return in_array($terminal, $this->nonOutput, true);
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
        $output = $this->resolveSequence($sequence, $plan, fn (int $count): int => $this->faker->numberBetween(0, $count - 1));
        return (new SqlSerializer())->serialize($output->pieces());
    }

    /**
     * Exposes the resolved choices to a plan compiler without interpreting its input.
     * @param GenerationPlan<bool>|null $plan
     * @param Closure(int): int $choose
     * @param (Closure(positive-int): ?int)|null $valueChoice Constructive values selected only while compiling a plan
     * @throws LexicalException When no compatible candidate exists
     */
    #[Override]
    public function resolveSequence(TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose, ?Closure $valueChoice = null): ResolvedOutput
    {
        return $this->pipeline->generate($sequence, $plan, $choose, $valueChoice);
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
