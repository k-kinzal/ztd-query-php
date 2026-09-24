<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Trigger\TransitionTable;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(TransitionTable::class)]
final class TransitionTableTest extends TestCase
{
    public function testATransitionTableRetainsItsVersionAndName(): void
    {
        $table = new TransitionTable(RowVersion::New, 'inserted');
        self::assertSame(RowVersion::New, $table->version);
        self::assertSame('inserted', $table->name);
    }

    public function testATransitionTableRequiresAName(): void
    {
        $this->expectException(InvalidStructure::class);
        new TransitionTable(RowVersion::Old, '');
    }
}
