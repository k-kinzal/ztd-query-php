<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity\Numeric;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\Numeric\IntegerStorage;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(IntegerStorage::class)]
#[Medium]
final class IntegerStorageTest extends TestCase
{
    public function testNameAppendsUnsignedToTheFamily(): void
    {
        $identity = new IntegerStorage(BuiltinIdentity::BigInt, new NumericParameter('20'), true);
        self::assertSame('bigint unsigned', $identity->name());
        self::assertSame('smallint', (new IntegerStorage(BuiltinIdentity::SmallInt))->name());
        self::assertSame('bigint(20) UNSIGNED', TypeDeclaration::write(new TypeDescriptor(Dialect::MySql, $identity))->toString());
    }

    public function testRejectsANonIntegerFamily(): void
    {
        $this->expectException(InvalidStructure::class);
        new IntegerStorage(BuiltinIdentity::Text);
    }

    public function testBindsDisplayWidthAndSignedness(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT(11) UNSIGNED, b BIGINT)')->tables[0];
        self::assertInstanceOf(IntegerStorage::class, $table->columns[0]->type->identity);
        self::assertInstanceOf(IntegerStorage::class, $table->columns[1]->type->identity);
        self::assertSame('11', $table->columns[0]->type->identity->displayWidth?->spelling);
        self::assertTrue($table->columns[0]->type->identity->unsigned);
        self::assertSame('integer unsigned', $table->columns[0]->type->name);
        self::assertFalse($table->columns[1]->type->identity->unsigned);
        self::assertNull($table->columns[1]->type->identity->displayWidth);
    }
}
