<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Function\Text\Radixes;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(Radixes::class)]
#[Small]
final class RadixesTest extends TestCase
{
    public function testRoutinesNamesTheBaseConversions(): void
    {
        self::assertSame(['CONV', 'BIN', 'OCT'], array_map(static fn ($routine): string => $routine->name, (new Radixes())->routines()));
    }

    public function testConvertReadsTheTextOfTheNumberInTheBase(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT BIN(1.9), BIN(1e20), BIN(x'41'), OCT(-1), CONV(255, 16, 10), CONV('-ff', 16, -10), CONV('1', 37, 10), BIN(''), CONV('-1', 10, 10)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1000001', '1777777777777777777777', '597', '-255', null, null, '18446744073709551615']], $result->rows);
        self::assertSame(260, $result->columns[0]->length);
    }

    public function testCharsetOfIsThatOfAString(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONV('٣', 10, 10)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['0']], $result->rows);
        self::assertSame([['Warning', '1292', "Truncated incorrect DECIMAL value: '٣'"]], $warnings->rows);
    }

    public function testUnsignedAnswersTheBitsOfANegativeInteger(): void
    {
        self::assertSame(['18446744073709551615', '5'], [(new Radixes())->unsigned(-1), (new Radixes())->unsigned(5)]);
    }

    public function testReadClampsANumberBeyond64Bits(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONV('-1fffffffffffffffff', 16, 10), CONV('-ffffffffffffffffff', 16, 10), CONV('ffffffffffffffffff', -16, 10), CONV('-ffffffffffffffffff', -16, -10)")[0];
        $legacy = (new Instance('5.7.44'))->connect()->query("SELECT CONV('-1fffffffffffffffff', 16, 10)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $legacy);
        self::assertSame([['0', '18446744073709551615', '9223372036854775807', '-9223372036854775808']], $result->rows);
        self::assertSame([['18446744073709551615']], $legacy->rows);
        self::assertSame(Charset::known('utf8mb4'), (new Radixes())->charsetOf(\MySqlMemory\Typing\Domain::integer()));
    }

    public function testMagnitudeReadsTheLeadingDigitsOfTheBase(): void
    {
        self::assertSame([['255', 2], ['0', 0], ['5', 3]], [(new Radixes())->magnitude('ffg1', 16), (new Radixes())->magnitude('z', 10), (new Radixes())->magnitude('1012', 2)]);
    }

    public function testMagnitudeReadsNoDigitOfAnEmptyText(): void
    {
        self::assertSame(['0', 0], (new Radixes())->magnitude('', 10));
    }

    public function testWriteUsesUpperCaseDigits(): void
    {
        self::assertSame(['0', 'FF', 'Z'], [(new Radixes())->write('0', 16), (new Radixes())->write('255', 16), (new Radixes())->write('35', 36)]);
    }
}
