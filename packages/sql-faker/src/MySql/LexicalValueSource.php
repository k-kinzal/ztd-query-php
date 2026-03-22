<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

interface LexicalValueSource
{
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 64): string;

    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string;

    public function nationalStringLiteral(int $minLength = 1, int $maxLength = 32): string;

    public function dollarQuotedString(int $minLength = 1, int $maxLength = 32): string;

    public function integerLiteral(int $min = 1, int $max = 2147483647): string;

    public function longIntegerLiteral(int $min = 0, int $max = 2147483647): string;

    public function unsignedBigIntLiteral(int $minLength = 1, int $maxLength = 20): string;

    public function decimalLiteral(int $precision = 10, int $scale = 2): string;

    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -38, int $maxExponent = 38): string;

    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string;

    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string;

    public function hostname(int $minParts = 1, int $maxParts = 1, int $maxPartLength = 16): string;

    public function filterWildcardPattern(int $maxPartLength = 12): string;

    public function resetMasterIndex(): string;
}
