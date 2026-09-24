<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Composite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Composite\CompositeAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CompositeAttribute::class)]
final class CompositeAttributeTest extends TestCase
{
    public function testRetainsTheDeclaration(): void
    {
        $attribute = new CompositeAttribute('a', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), new QualifiedName(['C']));
        self::assertSame('a', $attribute->name);
        self::assertSame('text', $attribute->type->name);
        self::assertSame(['C'], $attribute->collation?->parts);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new CompositeAttribute('', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
    }

    public function testRejectsATypeOfAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new CompositeAttribute('a', TypeDescriptor::builtin(Dialect::MySql, 'text'));
    }

    public function testRejectsAnOverQualifiedCollation(): void
    {
        $this->expectException(InvalidStructure::class);
        new CompositeAttribute('a', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), new QualifiedName(['a', 'b', 'c']));
    }
}
