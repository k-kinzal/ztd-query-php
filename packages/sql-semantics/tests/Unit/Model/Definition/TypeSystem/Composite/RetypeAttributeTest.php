<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Composite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Composite\RetypeAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(RetypeAttribute::class)]
final class RetypeAttributeTest extends TestCase
{
    public function testRetainsTheOperands(): void
    {
        $change = new RetypeAttribute('a', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), new QualifiedName(['C']), DropBehavior::Cascade);
        self::assertSame('a', $change->name);
        self::assertSame(['C'], $change->collation?->parts);
        self::assertSame(DropBehavior::Cascade, $change->behavior);
    }

    public function testRejectsATypeOfAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new RetypeAttribute('a', TypeDescriptor::builtin(Dialect::Sqlite, 'text'));
    }

    public function testRejectsAnOverQualifiedCollation(): void
    {
        $this->expectException(InvalidStructure::class);
        new RetypeAttribute('a', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), new QualifiedName(['a', 'b', 'c']));
    }
}
