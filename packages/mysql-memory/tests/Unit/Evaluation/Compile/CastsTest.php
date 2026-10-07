<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\Casts;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Casts::class)]
#[Small]
final class CastsTest extends TestCase
{
    public function testCastCutsAStringToTheLengthOfTheTargetAndWarns(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CAST(12.5 AS CHAR(2))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['12']], $result->rows);
        self::assertSame([['Warning', 1292, "Truncated incorrect CHAR(2) value: '12.5'"]], $session->diagnostics->conditions);
    }

    public function testCastConvertsToTheNumericTargets(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('12abc' AS SIGNED), CAST(1.567 AS DECIMAL(4,2)), CAST(-1 AS UNSIGNED)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['12', '1.57', '18446744073709551615']], $result->rows);
        self::assertSame(['Warning', 1292, "Truncated incorrect INTEGER value: '12abc'"], $session->diagnostics->conditions[0]);
    }

    public function testCastConvertsToTheTemporalTargets(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('2024-02-29' AS DATE), CAST(TIMESTAMP '2024-02-29 10:11:12' AS TIME), CAST(20240229 AS DATETIME)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-02-29', '10:11:12', '2024-02-29 00:00:00']], $result->rows);
    }

    public function testCastGivesTheResultTheTypeOfTheTarget(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CAST(1.567 AS DECIMAL(4,2))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['NewDecimal', 2], [$result->columns[0]->type->name, $result->columns[0]->decimals]);
    }
}
