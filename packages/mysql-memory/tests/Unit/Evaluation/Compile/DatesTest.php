<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\Dates;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dates::class)]
#[Small]
final class DatesTest extends TestCase
{
    public function testArithmeticMovesADateByAnInterval(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE '2024-01-31' + INTERVAL 1 MONTH, '2018-12-31' + INTERVAL 1 DAY, '2025-01-01' - INTERVAL 1 SECOND")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-02-29', '2019-01-01', '2024-12-31 23:59:59']], $result->rows);
    }

    public function testAdditionMovesADateByAnIntervalWrittenFirst(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT INTERVAL 1 DAY + '2018-12-31', INTERVAL 1 DAY + DATE '2024-02-28'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2019-01-01', '2024-02-29']], $result->rows);
    }

    public function testCallCompilesDateAddAndDateSub(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE_ADD('2018-05-01', INTERVAL 1 DAY), DATE_SUB('2018-05-01', INTERVAL 1 YEAR), DATE_ADD('2020-12-31 23:59:59', INTERVAL 1 SECOND)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2018-05-02', '2017-05-01', '2021-01-01 00:00:00']], $result->rows);
    }

    public function testShiftTurnsADateMovedByLessThanADayIntoADateTime(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE '2024-01-01' + INTERVAL 1 HOUR, DATE '2024-01-01' + INTERVAL 1 DAY")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-01 01:00:00', '2024-01-02']], $result->rows);
        self::assertSame(['DateTime', 'Date'], [$result->columns[0]->type->name, $result->columns[1]->type->name]);
    }

    public function testExtractWritesThePartsOfTheUnitAsOneInteger(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACT(YEAR FROM '2019-07-02'), EXTRACT(YEAR_MONTH FROM '2019-07-02 01:02:03'), EXTRACT(DAY_MINUTE FROM '2019-07-02 01:02:03'), EXTRACT(MICROSECOND FROM '2003-01-02 10:30:00.000123')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2019', '201907', '20102', '123']], $result->rows);
    }

    public function testExtractReadsTheSignOfANegativeTime(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACT(HOUR FROM TIME '-12:30:00'), EXTRACT(MINUTE FROM TIME '-12:30:00')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-12', '-30']], $result->rows);
    }

    public function testExtractOfNullIsNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT EXTRACT(HOUR FROM NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }
}
