<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Literal as L;
use SqlSemantics\Statement\Validation\Correspondence\LiteralMatch;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

#[CoversClass(LiteralMatch::class)]
#[Small]
final class LiteralMatchTest extends TestCase
{
    #[DataProvider('providerEqualLiterals')]
    public function testCheckAcceptsEqualImmutableValuesWithSeparateIdentities(E\NullConstant|E\SqliteInteger|E\SqliteReal|E\SqliteText|E\SqliteBlob $expected, E\ScalarExpression $actual): void
    {
        (new LiteralMatch())->check($expected, $actual);
        self::assertNotSame($expected, $actual);
    }

    /**
     * @return list<array{E\NullConstant|E\SqliteInteger|E\SqliteReal|E\SqliteText|E\SqliteBlob, E\ScalarExpression}>
     */
    public static function providerEqualLiterals(): array
    {
        return [
            [new E\NullConstant('null'), new E\NullConstant('null')],
            [new E\SqliteInteger(new L\UnsignedInteger('12345678901234567890123456789')), new E\SqliteInteger(new L\UnsignedInteger('12345678901234567890123456789'))],
            [new E\SqliteReal('1.0000000000000000000000001e-30'), new E\SqliteReal('1.0000000000000000000000001e-30')],
            [new E\SqliteText(new L\StringLiteral("a'b\\c")), new E\SqliteText(new L\StringLiteral("a'b\\c"))],
            [new E\SqliteBlob(new L\BinaryLiteral("\xAB"), true, 'Ab'), new E\SqliteBlob(new L\BinaryLiteral("\xAB"), true, 'Ab')],
        ];
    }

    #[DataProvider('providerDifferentLiterals')]
    public function testCheckRejectsChangesThatFloatConversionOrSqlTextComparisonCouldHide(E\NullConstant|E\SqliteInteger|E\SqliteReal|E\SqliteText|E\SqliteBlob|E\SqliteCurrentTime $expected, E\ScalarExpression $actual): void
    {
        $this->expectException(InvariantViolation::class);
        (new LiteralMatch())->check($expected, $actual);
    }

    /**
     * @return list<array{E\NullConstant|E\SqliteInteger|E\SqliteReal|E\SqliteText|E\SqliteBlob|E\SqliteCurrentTime, E\ScalarExpression}>
     */
    public static function providerDifferentLiterals(): array
    {
        return [
            [new E\SqliteInteger(new L\UnsignedInteger('9007199254740992')), new E\SqliteInteger(new L\UnsignedInteger('9007199254740993'))],
            [new E\SqliteInteger(new L\UnsignedInteger('10', L\Radix::Hexadecimal)), new E\SqliteInteger(new L\UnsignedInteger('10'))],
            [new E\SqliteInteger(new L\UnsignedInteger('1')), new E\SqliteInteger(new L\UnsignedInteger('1'), true)],
            [new E\SqliteReal('1.000000000000000000001'), new E\SqliteReal('1.000000000000000000002')],
            [new E\SqliteText(new L\StringLiteral('1')), new E\SqliteInteger(new L\UnsignedInteger('1'))],
            [new E\SqliteBlob(new L\BinaryLiteral('A')), new E\SqliteText(new L\StringLiteral('A'))],
            [new E\NullConstant('null'), new E\NullConstant('NULL')],
            [new E\SqliteBlob(new L\BinaryLiteral("\xAB"), true, 'Ab'), new E\SqliteBlob(new L\BinaryLiteral("\xAB"), true, 'ab')],
            [E\SqliteCurrentTime::Date, E\SqliteCurrentTime::Timestamp],
        ];
    }

    public function testCheckKeepsAClockRequestWithoutReadingTheHostClock(): void
    {
        (new LiteralMatch())->check(E\SqliteCurrentTime::Time, E\SqliteCurrentTime::Time);
        self::assertSame('CURRENT_TIME', E\SqliteCurrentTime::Time->value);
    }
}
