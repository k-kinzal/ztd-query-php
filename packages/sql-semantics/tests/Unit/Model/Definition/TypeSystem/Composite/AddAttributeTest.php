<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Composite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Composite\AddAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Composite\CompositeAttribute;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(AddAttribute::class)]
final class AddAttributeTest extends TestCase
{
    public function testRetainsTheAttributeAndPolicy(): void
    {
        $change = new AddAttribute(new CompositeAttribute('a', TypeDescriptor::builtin(Dialect::PostgreSql, 'text')), DropBehavior::Cascade);
        self::assertSame('a', $change->attribute->name);
        self::assertSame(DropBehavior::Cascade, $change->behavior);
    }
}
