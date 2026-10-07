<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Evaluation\Function\Control;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Control::class)]
#[Small]
final class ControlTest extends TestCase
{
    public function testRoutinesNamesTheFlowControlFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Control())->routines());

        self::assertSame(['IF', 'IFNULL', 'COALESCE', 'NULLIF', 'ISNULL'], $names);
    }

    public function testIfChoosesTheElseBranchForAFalseOrNullCondition(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT IF(1 > 0, 1, 2), IF(0, 1, 2), IF(NULL, 1, 2)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '2', '2']], $result->rows);
    }

    public function testCoalesceAnswersTheFirstValueThatIsNotNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT COALESCE(NULL, NULL, 3, 4), COALESCE(NULL, NULL), IFNULL(NULL, 'b'), IFNULL('a', 'b')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', null, 'b', 'a']], $result->rows);
    }

    public function testCoalesceConvertsTheValueToTheAggregatedType(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT COALESCE(NULL, 1, 2.50), IFNULL(NULL, 2) + 0.5')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1.00', '2.5']], $result->rows);
    }

    public function testNullifAnswersNullWhenBothArgumentsAreEqual(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT NULLIF(1, 1), NULLIF(1, 2), NULLIF('a', 'A'), NULLIF(NULL, 1), NULLIF(1, NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '1', null, null, '1']], $result->rows);
    }

    public function testRoutinesIsNullAnswersOneForNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ISNULL(NULL), ISNULL(0), ISNULL(1 / 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1']], $result->rows);
    }
}
