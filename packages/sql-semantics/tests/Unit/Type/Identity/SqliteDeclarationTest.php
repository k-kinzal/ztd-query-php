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
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Identity\SqliteDeclaration;
use SqlSemantics\Type\Identity\StorageAffinity;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(SqliteDeclaration::class)]
#[Medium]
final class SqliteDeclarationTest extends TestCase
{
    public function testNameIsTheDeclaredSpelling(): void
    {
        $identity = new SqliteDeclaration('DECIMAL', StorageAffinity::Numeric, new NumericParameter('10'), new NumericParameter('2'));
        $type = new TypeDescriptor(Dialect::Sqlite, $identity);
        self::assertSame('DECIMAL', $identity->name());
        self::assertSame('DECIMAL', $type->name);
        self::assertSame(StorageAffinity::Numeric, $type->affinity);
        self::assertSame('"DECIMAL"(10, 2)', TypeDeclaration::write($type)->toString());
    }

    public function testRejectsAScaleWithoutASize(): void
    {
        $this->expectException(InvalidStructure::class);
        new SqliteDeclaration('numeric', StorageAffinity::Numeric, null, new NumericParameter('2'));
    }

    public function testRequiresTheSqliteDialectOnTheDescriptor(): void
    {
        $this->expectException(InvalidStructure::class);
        new TypeDescriptor(Dialect::PostgreSql, new SqliteDeclaration('numeric', StorageAffinity::Numeric));
    }

    public function testBindsDeclaredParametersAndAnUntypedColumn(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a NUMERIC(10,2), b)')->tables[0];
        self::assertInstanceOf(SqliteDeclaration::class, $table->columns[0]->type->identity);
        self::assertInstanceOf(SqliteDeclaration::class, $table->columns[1]->type->identity);
        self::assertSame('10', $table->columns[0]->type->identity->size?->spelling);
        self::assertSame('2', $table->columns[0]->type->identity->scale?->spelling);
        self::assertSame('', $table->columns[1]->type->identity->declaredName);
        self::assertSame(StorageAffinity::Blob, $table->columns[1]->type->affinity);
    }
}
