<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use stdClass;
use ZtdQuery\Adapter\Pdo\Session\BufferedRow;

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
    public function testResolveModeUsesExplicitThenStatementThenConnectionMode(): void
    {
        $row = new BufferedRow();
        self::assertSame(PDO::FETCH_NUM, $row->resolveMode(PDO::FETCH_NUM, PDO::FETCH_ASSOC, PDO::FETCH_OBJ));
        self::assertSame(PDO::FETCH_OBJ, $row->resolveMode(PDO::FETCH_DEFAULT, PDO::FETCH_ASSOC, PDO::FETCH_OBJ));
        self::assertSame(PDO::FETCH_ASSOC, $row->resolveMode(PDO::FETCH_DEFAULT, PDO::FETCH_ASSOC, null));
    }


    public function testFetchPreservesValues(): void
    {
        $rows = new BufferedRow();
        self::assertSame(['id' => 7, 'name' => null], $rows->fetch(['id' => 7, 'name' => null], PDO::FETCH_ASSOC));
    }

    public function testAllShapesRowsAndSelectsTheRequestedColumn(): void
    {
        $rows = new BufferedRow();
        self::assertSame(['Ada', 'Grace'], $rows->all([['id' => 7, 'name' => 'Ada'], ['id' => 9, 'name' => 'Grace']], PDO::FETCH_COLUMN, [1]));
        self::assertSame([7], $rows->all([['id' => 7]], PDO::FETCH_COLUMN, ['invalid column']));
        self::assertSame([[7, 'Ada']], $rows->all([['id' => 7, 'name' => 'Ada']], PDO::FETCH_NUM, []));
    }

    public function testColumnPreservesNullAndMissingColumnBehavior(): void
    {
        $rows = new BufferedRow();
        self::assertSame('Ada', $rows->column(['id' => 7, 'name' => 'Ada'], 1));
        self::assertFalse($rows->column(['id' => 7], 1));
        self::assertFalse($rows->column(['name' => null], 0));
    }

    /**
     * @throws ReflectionException
     */
    public function testObjectPreservesAnonymousRowsAndEndOfCursor(): void
    {
        $rows = new BufferedRow();
        $object = $rows->object(['id' => 7, 'name' => null], null, []);
        self::assertInstanceOf(stdClass::class, $object);
        self::assertSame(['id' => 7, 'name' => null], get_object_vars($object));
        self::assertFalse($rows->object(false, null, []));
    }

    /**
     * @throws ReflectionException
     */
    public function testObjectHydratesDeclaredPropertiesAfterConstruction(): void
    {
        $prototype = new class ('initial') {
            public function __construct(public string $name)
            {
            }
        };
        $object = (new BufferedRow())->object(['name' => 'Ada', 'unknown' => 7], $prototype::class, ['constructor']);
        self::assertInstanceOf($prototype::class, $object);
        self::assertSame(['name' => 'Ada'], get_object_vars($object));
    }
}
