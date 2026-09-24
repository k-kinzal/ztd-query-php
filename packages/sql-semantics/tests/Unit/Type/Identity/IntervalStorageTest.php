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
use SqlSemantics\Type\Identity\IntervalFields;
use SqlSemantics\Type\Identity\IntervalStorage;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(IntervalStorage::class)]
#[Medium]
final class IntervalStorageTest extends TestCase
{
    public function testNameIsIntervalRegardlessOfTheFieldRange(): void
    {
        $identity = new IntervalStorage(IntervalFields::HourToMinute);
        self::assertSame('interval', $identity->name());
        self::assertSame(IntervalFields::All, (new IntervalStorage())->fields);
        self::assertNull($identity->precision);
        self::assertSame('INTERVAL HOUR TO MINUTE', TypeDeclaration::write(new TypeDescriptor(Dialect::PostgreSql, $identity))->toString());
    }

    public function testRequiresThePostgreSqlDialectOnTheDescriptor(): void
    {
        $this->expectException(InvalidStructure::class);
        new TypeDescriptor(Dialect::MySql, new IntervalStorage());
    }

    public function testBindsTheFieldRangeAndFractionalPrecision(): void
    {
        $type = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(d INTERVAL DAY TO SECOND(3))')->tables[0]->columns[0]->type;
        self::assertInstanceOf(IntervalStorage::class, $type->identity);
        self::assertSame(IntervalFields::DayToSecond, $type->identity->fields);
        self::assertSame('3', $type->identity->precision?->spelling);
        self::assertSame('INTERVAL DAY TO SECOND(3)', TypeDeclaration::write($type)->toString());
    }
}
