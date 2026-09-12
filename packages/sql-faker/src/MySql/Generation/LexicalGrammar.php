<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation;

use Closure;
use Faker\Generator as FakerGenerator;
use InvalidArgumentException;
use Override;
use RuntimeException;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Lexeme\LexicalGrammar as LexicalGrammarContract;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Generation\Output\SqlSerializer;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\DefinitionFactory;
use SqlFaker\MySql\Generation\Tokenization\KeywordIndex;
use SqlFaker\MySql\Generation\Tokenization\MySqlTokenizer;
use SqlFaker\MySql\Generation\Value\LiteralGenerator;

/**
 * MySQL lexical generation using source-based candidate and boundary definitions.
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
    private MySqlTokenizer $tokenizer;

    /**
     * @param FakerGenerator $faker Source of the choices realization makes
     * @param string $profileVersion Exact server version to generate for, e.g. "mysql-8.4.7"
     * @param KeywordIndex|null $index Inverts the profile's terminal-to-spelling maps
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
        $this->tokenizer = new MySqlTokenizer(
            $index->reversed($definition->keywords),
            $index->reversed($definition->functions),
            in_array($profileVersion, $definition->dollarVersions, true),
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
     * Reports parser markers that intentionally write no characters.
     */
    #[Override]
    public function isNonOutput(string $terminal): bool
    {
        return in_array($terminal, $this->nonOutput, true);
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
     * Resolves candidates and boundaries from right to left, then concatenates the chosen output.
     * @param GenerationPlan<bool>|null $plan
     * @throws LexicalException When a terminal has no applicable realization
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
