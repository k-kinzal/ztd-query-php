<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers::class)]
#[Medium]
final class ObjectIdentifiersTest extends TestCase
{
    public function testCheckReportsEveryNumberThatIsNoObjectIdentifier(): void
    {
        self::assertSame(['invalid input syntax for type oid: "1.5"', 'invalid input syntax for type oid: "-1e2"', 'value "4294967296" is out of range for type oid', 'value "-2147483649" is out of range for type oid'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REVOKE SELECT ON LARGE OBJECT 1.5, -1e2, 16385, 4294967296, -2147483649 FROM joe')->facts->diagnostics));
    }

    public function testCheckAcceptsTheIdentifiersTheServerReads(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT ON LARGE OBJECT 0, -7, 2147483647, 4294967295, -2147483648 TO joe')->facts->diagnostics));
    }

    public function testCheckReadsTheWrittenTextOfANumericConstantAsStrtoulDoes(): void
    {
        self::assertSame(['invalid input syntax for type oid: "1_0000000000"', 'invalid input syntax for type oid: "0o40000000000"', 'value "0x100000000" is out of range for type oid'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT ON LARGE OBJECT 0x80000000, 1_0000000000, 0o40000000000, 03000000000, 0xFFFFFFFF, -0x80000000, 0x100000000 TO joe')->facts->diagnostics));
    }

    public function testProblemAcceptsEveryIntegerConstant(): void
    {
        self::assertNull((new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->problem(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(true, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2147483647'))));
    }

    public function testProblemRejectsANegativeValueBeyondTheSignExtension(): void
    {
        self::assertSame('value "-4294967295" is out of range for type oid', (new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->problem(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(true, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant('4294967295')))?->message());
    }

    public function testProblemRejectsAValueOfMoreThanTenDigits(): void
    {
        self::assertSame('value "10000000000" is out of range for type oid', (new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->problem(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant('10000000000')))?->message());
    }

    public function testUnsignedReadsDecimalHexadecimalAndOctalDigits(): void
    {
        self::assertSame(['3000000000', '8589934591', '402653184'], [(new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->unsigned('3000000000'), (new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->unsigned('0X1ffffffff'), (new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->unsigned('03000000000')]);
    }

    public function testUnsignedReadsNothingFromAnyOtherText(): void
    {
        self::assertSame([null, null, null, null, null], [(new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->unsigned('1.5'), (new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->unsigned('1e10'), (new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->unsigned('3_000_000_000'), (new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->unsigned('0b100000000000000000000000000000000'), (new \SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers())->unsigned('08000000000')]);
    }
}
