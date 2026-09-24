<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Relation;

#[CoversClass(Relation\Partition\PartitionDetachMode::class)]
#[Medium]
final class PartitionDetachModeTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['', 'CONCURRENTLY', 'FINALIZE'], array_column(Relation\Partition\PartitionDetachMode::cases(), 'value'));
    }
}
