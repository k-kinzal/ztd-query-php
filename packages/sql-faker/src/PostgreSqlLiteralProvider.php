<?php

declare(strict_types=1);

namespace SqlFaker;

use Faker\Generator;
use Faker\Provider\Base;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\PostgreSql\GenerationPlans;
use SqlFaker\PostgreSql\SqlGenerator;

/**
 * Faker provider for the values a statement is written with.
 *
 * Every literal PostgreSQL admits, spelled the way its grammar spells it.
 *
 * PostgreSqlProvider registers this alongside itself, so a caller adds that
 * one provider and reaches these through the generator like any other Faker
 * method.
 */
final class PostgreSqlLiteralProvider extends Base
{
    /** @readonly */
    private SqlGenerator $sql;

    /**
     * Binds the provider to the generator it answers through.
     *
     * @param Generator $generator Generator the methods are reached through
     * @param string|null $version Version tag to generate for, or null for the default
     */
    public function __construct(Generator $generator, ?string $version = null)
    {
        parent::__construct($generator);

        $this->sql = SqlGenerator::for($generator, $version);

        $generator->addProvider($this);
    }

    /**
     * Generate a PostgreSQL identifier via grammar derivation.
     *
     * @param int $maxDepth Maximum recursion depth
     * @return non-empty-string
     */
    public function identifier(int $maxDepth = PHP_INT_MAX): string
    {
        return $this->sql->generate(GenerationPlan::fromRule('ColId')->requiringNonEmpty()->withMaxDepth($maxDepth));
    }

    /**
     * Generate a double-quote-quoted PostgreSQL identifier.
     *
     * @return non-empty-string
     */
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 63): string
    {
        return $this->sql->generate(GenerationPlans::quotedIdentifier($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL string literal.
     *
     * @return non-empty-string
     */
    public function stringLiteral(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::stringLiteral($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL integer literal.
     *
     * @return non-empty-string
     */
    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->sql->generate(GenerationPlans::integerLiteral($min, $max));
    }

    /**
     * Generate a PostgreSQL decimal literal.
     *
     * @return non-empty-string
     */
    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->sql->generate(GenerationPlans::decimalLiteral($precision, $scale));
    }

    /**
     * Generate a PostgreSQL float literal with exponent (FCONST).
     *
     * @return non-empty-string
     */
    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -307, int $maxExponent = 308): string
    {
        return $this->sql->generate(
            GenerationPlans::floatLiteral($precision, $scale, $minExponent, $maxExponent),
        );
    }

    /**
     * Generate a PostgreSQL hexadecimal literal (X'...' / XCONST).
     *
     * @return non-empty-string
     */
    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return $this->sql->generate(GenerationPlans::hexLiteral($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL bit string literal (B'...' / BCONST).
     *
     * @return non-empty-string
     */
    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return $this->sql->generate(GenerationPlans::binaryLiteral($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL dollar-quoted string ($$...$$).
     *
     * @return non-empty-string
     */
    public function dollarQuotedString(int $minLength = 1, int $maxLength = 255): string
    {
        return $this->sql->generate(GenerationPlans::dollarQuotedString($minLength, $maxLength));
    }

    /**
     * Generate a PostgreSQL parameter marker ($1, $2, etc.).
     *
     * @return non-empty-string
     */
    public function parameterMarker(int $min = 1, int $max = 99): string
    {
        return $this->sql->generate(GenerationPlans::parameterMarker($min, $max));
    }
}
