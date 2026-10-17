<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Function\Time\Spans;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Spans::class)]
#[Small]
final class SpansTest extends TestCase
{
    public function testDifferenceCountsWholeUnits(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT TIMESTAMPDIFF(MONTH, '2024-01-31', '2024-02-29'), TIMESTAMPDIFF(MONTH, '2024-02-29', '2024-01-31'), TIMESTAMPDIFF(MONTH, '2024-03-31', '2024-02-29'), TIMESTAMPDIFF(YEAR, '2020-02-29', '2021-02-28'), TIMESTAMPDIFF(HOUR, '2024-01-02', '2024-01-01 00:30'), TIMESTAMPDIFF(MICROSECOND, '2024-01-01', '2024-01-01 00:00:00.5'), TIMESTAMPDIFF(MINUTE, '10:00', '11:00')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertEquals([[0, 0, -1, 0, -23, 500000, null]], $reply->rows);
    }

    public function testWithinCountsFromTheStartOfTheMonth(): void
    {
        self::assertSame(86400000001, (new Spans())->within([2024, 1, 1, 0, 0, 0, 1]));
    }
}
