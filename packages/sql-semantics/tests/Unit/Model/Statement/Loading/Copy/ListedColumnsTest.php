<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\ListedColumns;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ListedColumns::class)]
final class ListedColumnsTest extends TestCase
{
    public function testKeepsTheColumnsInOrder(): void
    {
        self::assertSame(['b', 'a'], (new ListedColumns(['b', 'a']))->columns);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new ListedColumns(['a', '']);
    }
}
