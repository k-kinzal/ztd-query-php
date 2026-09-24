<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\Identity\ArrayDimension;
use SqlSemantics\Type\Identity\ArrayStorage;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ArrayStorage::class)]
#[Medium]
final class ArrayStorageTest extends TestCase
{
    public function testNameAppendsBracketsToTheElementName(): void
    {
        $identity = new ArrayStorage(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), [new ArrayDimension(new NumericParameter('3')), new ArrayDimension()]);
        self::assertSame('text[]', $identity->name());
        self::assertSame('text [3] []', TypeDeclaration::write(new TypeDescriptor(Dialect::PostgreSql, $identity))->toString());
    }

    public function testRejectsAnArrayWithoutDimensions(): void
    {
        $this->expectException(InvalidStructure::class);
        new ArrayStorage(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), []);
    }

    public function testRejectsAnElementFromAnotherDialect(): void
    {
        $this->expectException(InvalidStructure::class);
        new ArrayStorage(TypeDescriptor::builtin(Dialect::MySql, 'integer'), [new ArrayDimension()]);
    }

    public function testRequiresThePostgreSqlDialectOnTheDescriptor(): void
    {
        $identity = new ArrayStorage(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), [new ArrayDimension()]);
        $this->expectException(InvalidStructure::class);
        new TypeDescriptor(Dialect::MySql, $identity);
    }

    public function testBindsAnArrayColumnType(): void
    {
        $type = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[3][])')->tables[0]->columns[0]->type;
        self::assertInstanceOf(ArrayStorage::class, $type->identity);
        self::assertSame('integer[]', $type->name);
        self::assertSame('integer', $type->identity->element->name);
        self::assertCount(2, $type->identity->dimensions);
    }
}
