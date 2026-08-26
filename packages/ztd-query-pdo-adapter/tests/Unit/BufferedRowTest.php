<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\BufferedRow;

#[CoversClass(BufferedRow::class)]
final class BufferedRowTest extends TestCase
{
    public function testInModeAnswersTheRowKeyedByColumnWhereThatIsAsked(): void
    {
        self::assertSame(
            ['id' => 1, 'name' => 'ada'],
            (new BufferedRow())->inMode(['id' => 1, 'name' => 'ada'], PDO::FETCH_ASSOC),
        );
    }

    public function testInModeAnswersANamedFetchTheSameWayAsAKeyedOne(): void
    {
        self::assertSame(
            ['id' => 1],
            (new BufferedRow())->inMode(['id' => 1], PDO::FETCH_NAMED),
        );
    }

    public function testInModeAnswersTheValuesAloneWhereOnlyPositionsAreAsked(): void
    {
        self::assertSame(
            [1, 'ada'],
            (new BufferedRow())->inMode(['id' => 1, 'name' => 'ada'], PDO::FETCH_NUM),
        );
    }

    public function testInModeAnswersAnObjectWhereOneIsAsked(): void
    {
        $object = (new BufferedRow())->inMode(['id' => 1], PDO::FETCH_OBJ);

        self::assertSame(['id' => 1], is_object($object) ? get_object_vars($object) : []);
    }

    public function testInModeAnswersTheFirstValueWhereOneColumnIsAsked(): void
    {
        self::assertSame(1, (new BufferedRow())->inMode(['id' => 1, 'name' => 'ada'], PDO::FETCH_COLUMN));
    }

    public function testInModeAnswersFalseWhereOneColumnIsAskedOfAnEmptyRow(): void
    {
        self::assertFalse((new BufferedRow())->inMode([], PDO::FETCH_COLUMN));
    }

    public function testInModeAnswersAModeItDoesNotKnowBothWaysAtOnce(): void
    {
        self::assertSame(
            ['id' => 1, 0 => 1],
            (new BufferedRow())->inMode(['id' => 1], PDO::FETCH_BOTH),
        );
    }

    public function testKeyedBothWaysReachesEveryValueUnderEitherKey(): void
    {
        self::assertSame(
            ['id' => 1, 0 => 1, 'name' => 'ada', 1 => 'ada'],
            (new BufferedRow())->keyedBothWays(['id' => 1, 'name' => 'ada']),
        );
    }

    public function testKeyedBothWaysAnswersNothingForARowWithNoColumns(): void
    {
        self::assertSame([], (new BufferedRow())->keyedBothWays([]));
    }
}
