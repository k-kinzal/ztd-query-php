<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use SqlFaker\Grammar\RandomStringGenerator;

final class LexicalValueGenerator implements LexicalValueSource
{
    private RandomStringGenerator $rsg;

    public function __construct(FakerGenerator $faker)
    {
        $this->rsg = new RandomStringGenerator($faker);
    }

    public function quotedIdentifier(int $minLength = 1, int $maxLength = 128): string
    {
        return '"' . $this->rsg->rawIdentifier($minLength, $maxLength) . '"';
    }

    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string
    {
        return "'" . $this->rsg->mixedAlnumString($minLength, $maxLength) . "'";
    }

    public function integerLiteral(int $min = 1, int $max = PHP_INT_MAX): string
    {
        return $this->rsg->integerString($min, $max);
    }

    public function decimalLiteral(int $precision = 15, int $scale = 2): string
    {
        return $this->rsg->decimalString($precision, $scale);
    }
}
