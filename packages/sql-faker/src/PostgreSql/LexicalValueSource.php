<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

interface LexicalValueSource
{
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 63): string;

    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string;

    public function integerLiteral(int $min = 1, int $max = 2147483647): string;

    public function decimalLiteral(int $precision = 10, int $scale = 2): string;

    public function floatLiteral(int $precision = 10, int $scale = 2, int $minExponent = -307, int $maxExponent = 308): string;

    public function hexLiteral(int $minLength = 1, int $maxLength = 16): string;

    public function binaryLiteral(int $minLength = 1, int $maxLength = 64): string;

    public function dollarQuotedString(int $minLength = 1, int $maxLength = 32): string;

    public function doBodyLiteral(): string;

    public function parameterMarker(int $min = 1, int $max = 99): string;
}
