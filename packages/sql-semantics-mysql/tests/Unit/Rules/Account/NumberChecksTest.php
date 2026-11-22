<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Account\NumberChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(NumberChecks::class)]
#[Medium]
final class NumberChecksTest extends TestCase
{
    public function testValueComputesTheExactDecimal(): void
    {
        $checks = new NumberChecks();

        self::assertSame(['18446744073709551615', '7', '0', null], [$checks->value(new Numeral('FFFFFFFFFFFFFFFF', true)), $checks->value(new Numeral('007')), $checks->value(new Numeral('000')), $checks->value(new Numeral('1.5'))]);
    }

    public function testCompareOrdersByLengthThenDigits(): void
    {
        self::assertSame([true, true, true], [(new NumberChecks())->compare('9', '10') < 0, (new NumberChecks())->compare('20', '19') > 0, (new NumberChecks())->compare('5', '5') === 0]);
    }

    public function testRangeReportsANumberOutsideTheRange(): void
    {
        $semantics = new Inspection();

        self::assertSame('The value 65536 is not accepted for DAY (ER_WRONG_VALUE).', $semantics->diagnose('CREATE USER u PASSWORD EXPIRE INTERVAL 65536 DAY')->facts->diagnostics[0]->message());
        self::assertSame([], $semantics->diagnose('CREATE USER u PASSWORD EXPIRE INTERVAL 65535 DAY')->facts->diagnostics);
    }

    public function testFactorReportsAFactorOtherThanTwoAndThree(): void
    {
        self::assertInstanceOf(NumberOutOfRange::class, (new Inspection())->diagnose('ALTER USER u 02 FACTOR UNREGISTER')->facts->diagnostics[0]);
    }
}
