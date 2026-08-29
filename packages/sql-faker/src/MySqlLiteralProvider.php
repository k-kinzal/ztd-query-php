<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\MySql\GenerationPlans;
use SqlFaker\MySql\SqlGenerator;

/**
 * Faker provider for the values a statement is written with.
 *
 * Every literal MySQL admits, spelled the way its grammar spells it: identifiers,
 * strings, the numeric kinds, the byte kinds, and a hostname.
 *
 * MySqlProvider registers this alongside itself, so a caller adds that one
 * provider and reaches these through the generator like any other Faker method.
 */
final class MySqlLiteralProvider extends Base
{
    /** @readonly */
    private SqlGenerator $sql;

    /**
     * Binds the provider to the generator it answers through.
     *
     * @param Generator $generator Generator the methods are reached through
     * @param string|null $version Version tag to generate for, or null for the default
     * @param SqlGenerator|null $sql Generator to share, or null to build one for this provider alone
     */
    public function __construct(Generator $generator, ?string $version = null, ?SqlGenerator $sql = null)
    {
        parent::__construct($generator);

        $this->sql = $sql ?? SqlGenerator::for($generator, $version);

        $generator->addProvider($this);
    }

    /**
     * Generate a MySQL identifier via grammar derivation.
     *
     * @param int $maxDepth Maximum recursion depth
     * @return string Generated identifier (e.g., "t1", "col42", "COMMIT")
     *
     * @example $faker->identifier() // "abc"
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlans::statement('ident', $maxDepth));
    }

    /**
     * Generate a backtick-quoted MySQL identifier.
     *
     * @return string Generated quoted identifier (e.g., "`abc`", "`x1`")
     *
     * @example $faker->quotedIdentifier() // "`abc`"
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 64): string
    {
        return $this->sql->generate(GenerationPlans::quotedIdentifier($minLength, $maxLength));
    }

    /**
     * Generate a MySQL string literal.
     *
     * @return string Generated string literal (e.g., "'abc123'")
     *
     * @example $faker->stringLiteral() // "'hello'"
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::stringLiteral($minLength, $maxLength));
    }

    /**
     * Generate a MySQL national string literal (N'...').
     *
     * @return string Generated national string literal (e.g., "N'abc'")
     *
     * @example $faker->nationalStringLiteral() // "N'hello'"
     */
    public function nationalStringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::nationalStringLiteral($minLength, $maxLength));
    }

    /**
     * Generate a MySQL dollar-quoted string ($$...$$).
     *
     * @return string Generated dollar-quoted string (e.g., "$$abc$$")
     *
     * @example $faker->dollarQuotedString() // "$$hello$$"
     */
    public function dollarQuotedString(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::dollarQuotedString($minLength, $maxLength));
    }

    /**
     * Generate a MySQL integer literal.
     *
     * @return string Generated integer literal (e.g., "42", "9876543210")
     *
     * @example $faker->integerLiteral() // "123"
     */
    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->sql->generate(GenerationPlans::integerLiteral($min, $max));
    }

    /**
     * Generate a MySQL long integer literal.
     *
     * @return string Generated long integer literal
     *
     * @example $faker->longIntegerLiteral() // "1234567890"
     */
    public function longIntegerLiteral(int $min = 0, int $max = 2147483647): string
    {
        return $this->sql->generate(GenerationPlans::longIntegerLiteral($min, $max));
    }

    /**
     * Generate a MySQL unsigned big integer literal.
     *
     * @return string Generated unsigned big integer literal
     *
     * @example $faker->unsignedBigIntLiteral() // "12345678901234567890"
     */
    public function unsignedBigIntLiteral(int $minLength = 1, int $maxLength = 20): string
    {
        return $this->sql->generate(GenerationPlans::unsignedBigIntLiteral($minLength, $maxLength));
    }

    /**
     * Generate a MySQL decimal literal.
     *
     * @return string Generated decimal literal (e.g., "123.45")
     *
     * @example $faker->decimalLiteral() // "99.50"
     */
    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->sql->generate(GenerationPlans::decimalLiteral($precision, $scale));
    }

    /**
     * Generate a MySQL float literal with exponent.
     *
     * @return string Generated float literal (e.g., "1.23e10")
     *
     * @example $faker->floatLiteral() // "3.14e-5"
     */
    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -38, int $maxExponent = 38): string
    {
        return $this->sql->generate(
            GenerationPlans::floatLiteral($precision, $scale, $minExponent, $maxExponent),
        );
    }

    /**
     * Generate a MySQL hexadecimal literal.
     *
     * @return string Generated hex literal (e.g., "0x1a2b3c")
     *
     * @example $faker->hexLiteral() // "0xdeadbeef"
     */
    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return $this->sql->generate(GenerationPlans::hexLiteral($minLength, $maxLength));
    }

    /**
     * Generate a quoted MySQL hexadecimal literal.
     *
     * @return string Generated quoted hex literal (e.g., "X'deadbeef'")
     *
     * @example $faker->quotedHexLiteral() // "X'deadbeef'"
     */
    public function quotedHexLiteral(int $minBytes = 1, int $maxBytes = 8): string
    {
        return $this->sql->generate(GenerationPlans::quotedHexLiteral($minBytes, $maxBytes));
    }

    /**
     * Generate a MySQL binary literal.
     *
     * @return string Generated binary literal (e.g., "0b1010")
     *
     * @example $faker->binaryLiteral() // "0b11001010"
     */
    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return $this->sql->generate(GenerationPlans::binaryLiteral($minLength, $maxLength));
    }

    /**
     * Generate a MySQL hostname.
     *
     * @return string Generated hostname (e.g., "a1b2.c3d4", "x")
     *
     * @example $faker->hostname() // "abc.def"
     */
    public function hostname(int $minParts = 1, int $maxParts = 4, int $maxPartLength = 63): string
    {
        return $this->sql->generate(GenerationPlans::hostname($minParts, $maxParts, $maxPartLength));
    }
}
