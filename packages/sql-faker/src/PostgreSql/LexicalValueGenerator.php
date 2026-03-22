<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use SqlFaker\Contract\RandomSource;
use SqlFaker\Grammar\RandomStringGenerator;

final class LexicalValueGenerator implements LexicalValueSource
{
    private RandomStringGenerator $rsg;

    public function __construct(RandomSource $random)
    {
        $this->rsg = new RandomStringGenerator($random);
    }

    public function quotedIdentifier(int $minLength = 1, int $maxLength = 63): string
    {
        return '"' . $this->rsg->rawIdentifier($minLength, $maxLength) . '"';
    }

    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string
    {
        return "'" . $this->rsg->mixedAlnumString($minLength, $maxLength) . "'";
    }

    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->rsg->integerString($min, $max);
    }

    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->rsg->decimalString($precision, $scale);
    }

    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -307, int $maxExponent = 308): string
    {
        return $this->rsg->floatString($this->decimalLiteral($precision, $scale), $minExponent, $maxExponent);
    }

    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return "X'" . $this->rsg->hexString($minLength, $maxLength) . "'";
    }

    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return "B'" . $this->rsg->binaryString($minLength, $maxLength) . "'";
    }

    public function dollarQuotedString(int $minLength = 1, int $maxLength = 32): string
    {
        return '$$' . $this->rsg->mixedAlnumString($minLength, $maxLength) . '$$';
    }

    public function doBodyLiteral(): string
    {
        return "'BEGIN NULL; END'";
    }

    public function parameterMarker(int $min = 1, int $max = 99): string
    {
        return '$' . $this->rsg->parameterIndex($min, $max);
    }
}
