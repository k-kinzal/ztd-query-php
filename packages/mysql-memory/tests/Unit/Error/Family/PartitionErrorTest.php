<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Family;

use MySqlMemory\Error\Family\PartitionError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartitionError::class)]
#[Small]
final class PartitionErrorTest extends TestCase
{
    public function testErrorAnswersTheNumberAndMessage(): void
    {
        $error = PartitionError::NoPartitionForValue->error('25');

        self::assertSame([1526, 'Table has no partition for value 25'], [$error->getCode(), $error->getMessage()]);
    }

    public function testNumberAnswersTheErrorNumber(): void
    {
        self::assertSame([1480, 1499], [PartitionError::WrongValuesKind->number(), PartitionError::TooManyPartitions->number()]);
    }

    public function testSqlStateAnswersTheStateOfTheCatalog(): void
    {
        self::assertSame(['HY000', 'HY000'], [PartitionError::WrongValuesKind->sqlState(), PartitionError::NoPartitionForValue->sqlState()]);
    }

    public function testMessageFillsTheMethodIntoTheFormat(): void
    {
        self::assertSame('Only RANGE PARTITIONING can use VALUES LESS THAN in partition definition', PartitionError::WrongValuesKind->message('RANGE', 'LESS THAN'));
    }
}
