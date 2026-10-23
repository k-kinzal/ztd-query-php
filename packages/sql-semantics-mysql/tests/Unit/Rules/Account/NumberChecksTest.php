<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Account\NumberChecks;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

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

    public function testProblemUsesConvertedValuesAndPreservesRejectedTokens(): void
    {
        $checks = new NumberChecks();
        self::assertSame('70905434', $checks->problem(new Numeral('000070905434'), 'FAILED_LOGIN_ATTEMPTS', '0', '32767')?->number);
        self::assertSame('32768', $checks->problem(new Numeral('8000', true), 'PASSWORD_LOCK_TIME', '0', '32767')?->number);
        self::assertSame('9223372036854775807', $checks->problem(new Numeral('10000000000000000', true), 'DAY', '1', '65535')?->number);
        self::assertSame('ER_ONLY_INTEGERS_ALLOWED', $checks->problem(new Numeral('00018446744073709551616'), 'PASSWORD HISTORY', '0', null)?->error);
        self::assertSame('00018446744073709551616', $checks->problem(new Numeral('00018446744073709551616'), 'PASSWORD HISTORY', '0', null)->number);
        self::assertNull($checks->problem(new Numeral('00032767'), 'PASSWORD_LOCK_TIME', '0', '32767'));
    }

    public function testOptionDistinguishesExpiryFromHistoryAndLockingDomains(): void
    {
        $checks = new NumberChecks();
        self::assertNotNull($checks->option(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::ExpireInterval, new Numeral('0')));
        self::assertNull($checks->option(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::HistoryCount, new Numeral('0')));
        self::assertNull($checks->option(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::ReuseInterval, new Numeral('18446744073709551615')));
        self::assertNotNull($checks->option(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::FailedLoginAttempts, new Numeral('32768')));
        self::assertNull($checks->option(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::LockTime, new Numeral('00032767')));
    }

    public function testRangeReportsANumberOutsideTheRange(): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        (new NumberChecks())->range($derivation, new Numeral('00065536'), 'DAY', '1', '65535');

        self::assertSame('The value 65536 is not accepted for DAY (ER_WRONG_VALUE).', $derivation->facts()->diagnostics[0]->message());
    }

}
