<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use SqlFaker\Grammar\RandomStringGenerator;

final class LexicalValueGenerator implements LexicalValueSource
{
    private RandomStringGenerator $rsg;

    public function __construct(FakerGenerator $faker)
    {
        $this->rsg = new RandomStringGenerator($faker);
    }

    public function quotedIdentifier(int $minLength = 1, int $maxLength = 64): string
    {
        return '`' . $this->rsg->rawIdentifier($minLength, $maxLength) . '`';
    }

    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string
    {
        return "'" . $this->rsg->mixedAlnumString($minLength, $maxLength) . "'";
    }

    public function nationalStringLiteral(int $minLength = 1, int $maxLength = 32): string
    {
        return 'N' . $this->stringLiteral($minLength, $maxLength);
    }

    public function dollarQuotedString(int $minLength = 1, int $maxLength = 32): string
    {
        return '$$' . $this->rsg->mixedAlnumString($minLength, $maxLength) . '$$';
    }

    public function integerLiteral(int $min = 1, int $max = 2147483647): string
    {
        return $this->rsg->integerString($min, $max);
    }

    public function longIntegerLiteral(int $min = 0, int $max = 2147483647): string
    {
        return $this->rsg->longIntString($min, $max);
    }

    public function unsignedBigIntLiteral(int $minLength = 1, int $maxLength = 20): string
    {
        return $this->rsg->unsignedBigIntString($minLength, $maxLength);
    }

    public function decimalLiteral(int $precision = 10, int $scale = 2): string
    {
        return $this->rsg->decimalString($precision, $scale);
    }

    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -38, int $maxExponent = 38): string
    {
        return $this->rsg->floatString($this->decimalLiteral($precision, $scale), $minExponent, $maxExponent);
    }

    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string
    {
        return '0x' . $this->rsg->hexString($minLength, $maxLength);
    }

    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string
    {
        return '0b' . $this->rsg->binaryString($minLength, $maxLength);
    }

    public function hostname(int $minParts = 1, int $maxParts = 1, int $maxPartLength = 16): string
    {
        return $this->rsg->hostnameString($minParts, $maxParts, 1, $maxPartLength);
    }

    public function filterWildcardPattern(int $maxPartLength = 12): string
    {
        return sprintf("'%s.%s'", $this->hostname(1, 1, $maxPartLength), $this->hostname(1, 1, $maxPartLength));
    }

    public function resetMasterIndex(): string
    {
        return $this->rsg->integerString(1, 2_000_000_000);
    }
}
