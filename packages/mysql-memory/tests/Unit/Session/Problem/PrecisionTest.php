<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Precision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Precision::class)]
#[Small]
final class PrecisionTest extends TestCase
{
    public function testCheckRefusesACastPrecisionAboveSix(): void
    {
        $session = (new Instance())->connect();
        (new Precision())->check($session->analyze('SELECT CAST(1 AS DATETIME(6)), CAST(1 AS DECIMAL(9))')->statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1426);
        $this->expectExceptionMessage("Too-big precision 7 specified for 'CAST'. Maximum is 6.");

        (new Precision())->check($session->analyze('SELECT CAST(1 AS TIME(7))')->statement);
    }

    public function testCheckRefusesAPrecisionAboveSixAtTimeZone(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1426);
        $this->expectExceptionMessage("Too-big precision 7 specified for 'CAST'. Maximum is 6.");

        (new Precision())->check($session->analyze("SELECT CAST(TIMESTAMP '2020-01-01 00:00:00' AT TIME ZONE '+00:00' AS DATETIME(7))")->statement);
    }

    public function testLegacyWritesTheMessageOfMySql56(): void
    {
        $error = \MySqlMemory\Error\Family\SchemaError::TooBigPrecision->error(9, 'CAST', 6);

        self::assertSame(["Too big precision 9 specified for column 'CAST'. Maximum is 6.", "Too-big precision 9 specified for 'CAST'. Maximum is 6."], [(new Precision())->legacy($error, \SqlSemantics\Contract\GrammarRelease::MySql5651)->getMessage(), (new Precision())->legacy($error, \SqlSemantics\Contract\GrammarRelease::MySql5744)->getMessage()]);
    }
}
