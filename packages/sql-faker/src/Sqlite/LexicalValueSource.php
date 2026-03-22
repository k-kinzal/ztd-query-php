<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

interface LexicalValueSource
{
    public function quotedIdentifier(int $minLength = 1, int $maxLength = 128): string;

    public function stringLiteral(int $minLength = 1, int $maxLength = 32): string;

    public function integerLiteral(int $min = 1, int $max = PHP_INT_MAX): string;

    public function decimalLiteral(int $precision = 15, int $scale = 2): string;
}
