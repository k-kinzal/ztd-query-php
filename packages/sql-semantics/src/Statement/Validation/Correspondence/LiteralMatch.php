<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Validation\Check;

/**
 * Compares exact literal requests and their profile-sensitive representation constraints.
 * @visibility SqlSemantics
 */
final class LiteralMatch
{
    /**
     * Numeric input never passes through a PHP float; equal values may be separate immutable objects.
     */
    public function check(E\NullConstant|E\SqliteInteger|E\SqliteReal|E\SqliteText|E\SqliteBlob|E\SqliteCurrentTime $expected, E\ScalarExpression $actual): void
    {
        $same = match (true) {
            $expected instanceof E\NullConstant => $actual instanceof E\NullConstant && $expected->keyword === $actual->keyword,
            $expected instanceof E\SqliteInteger => $actual instanceof E\SqliteInteger && [$expected->integer->digits, $expected->integer->radix, $expected->negative, $expected->uppercasePrefix] === [$actual->integer->digits, $actual->integer->radix, $actual->negative, $actual->uppercasePrefix],
            $expected instanceof E\SqliteReal => $actual instanceof E\SqliteReal && $expected->numeral === $actual->numeral,
            $expected instanceof E\SqliteText => $actual instanceof E\SqliteText && $expected->value->value === $actual->value->value,
            $expected instanceof E\SqliteBlob => $actual instanceof E\SqliteBlob && [$expected->value->value, $expected->digits, $expected->lowercasePrefix] === [$actual->value->value, $actual->digits, $actual->lowercasePrefix],
            $expected instanceof E\SqliteCurrentTime => $actual === $expected,
        };
        Check::invariant($same, 'An actual literal must retain the exact requested value, kind, and output-name constraints.');
    }
}
