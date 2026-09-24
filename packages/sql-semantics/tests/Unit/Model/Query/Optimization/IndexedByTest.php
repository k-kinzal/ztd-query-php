<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Optimization\IndexedBy;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(IndexedBy::class)]
#[Medium]
final class IndexedByTest extends TestCase
{
    public function testKeepsTheRequiredIndexName(): void
    {
        self::assertSame('ix', (new IndexedBy('ix'))->index);
    }

    public function testRejectsAnEmptyIndexName(): void
    {
        $this->expectException(InvalidStructure::class);
        new IndexedBy('');
    }
}
