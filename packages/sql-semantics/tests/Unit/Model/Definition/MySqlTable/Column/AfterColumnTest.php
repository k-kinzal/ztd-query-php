<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\MySqlTable\Column\AfterColumn;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(AfterColumn::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class AfterColumnTest extends TestCase
{
    public function testNamesTheColumn(): void
    {
        self::assertSame('id', (new AfterColumn('id'))->column);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new AfterColumn('');
    }
}
