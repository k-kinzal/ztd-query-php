<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Composite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Composite\DropAttribute;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DropAttribute::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DropAttributeTest extends TestCase
{
    public function testRetainsTheOperands(): void
    {
        $change = new DropAttribute('a', true, DropBehavior::Restrict);
        self::assertSame('a', $change->name);
        self::assertTrue($change->ifExists);
        self::assertSame(DropBehavior::Restrict, $change->behavior);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DropAttribute('');
    }
}
