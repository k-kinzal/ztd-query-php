<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionOptionKind;

#[CoversClass(PartitionOptionKind::class)]
#[Small]
final class PartitionOptionKindTest extends TestCase
{
    public function testNamedAnswersTheOptionsThatTakeAName(): void
    {
        self::assertTrue(PartitionOptionKind::Engine->named());
        self::assertFalse(PartitionOptionKind::Comment->named());
    }

    public function testNumberedAnswersTheOptionsThatTakeANumber(): void
    {
        self::assertTrue(PartitionOptionKind::MaxRows->numbered());
        self::assertFalse(PartitionOptionKind::Tablespace->numbered());
    }
}
