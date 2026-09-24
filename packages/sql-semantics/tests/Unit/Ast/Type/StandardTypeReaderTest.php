<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Type\StandardTypeReader;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity;

#[CoversClass(StandardTypeReader::class)]
#[Medium]
final class StandardTypeReaderTest extends TestCase
{
    public function testReadClassifiesPostgresNamedTypesWithTheirOperands(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build("CREATE TABLE t(a app.measure(currency, 'USD', 12), b pg_catalog.int4)")->tables[0];
        $measure = $table->columns[0]->type->identity;
        self::assertInstanceOf(Identity\NamedIdentity::class, $measure);
        self::assertSame(['app', 'measure'], $measure->reference->parts);
        self::assertCount(3, $measure->arguments);
        self::assertSame('app.measure', $table->columns[0]->type->name);
        $int4 = $table->columns[1]->type->identity;
        self::assertInstanceOf(Identity\NamedIdentity::class, $int4);
        self::assertSame(['pg_catalog', 'int4'], $int4->reference->parts);
        self::assertSame([], $int4->arguments);
    }

    public function testReadClassifiesIntervalFieldsAndPrecision(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTERVAL DAY TO SECOND(3), b INTERVAL)')->tables[0];
        $bounded = $table->columns[0]->type->identity;
        self::assertInstanceOf(Identity\IntervalStorage::class, $bounded);
        self::assertSame(Identity\IntervalFields::DayToSecond, $bounded->fields);
        self::assertSame('3', $bounded->precision?->spelling);
        $plain = $table->columns[1]->type->identity;
        self::assertInstanceOf(Identity\IntervalStorage::class, $plain);
        self::assertSame(Identity\IntervalFields::All, $plain->fields);
        self::assertNull($plain->precision);
    }

    public function testReadRoutesLabelSetsAndBuiltinFamilies(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build("CREATE TABLE t(a ENUM('x', 'y'), b SET('p'), c BOOLEAN)")->tables[0];
        self::assertInstanceOf(Identity\Enumeration::class, $table->columns[0]->type->identity);
        self::assertSame('enum', $table->columns[0]->type->name);
        self::assertInstanceOf(Identity\LabelSet::class, $table->columns[1]->type->identity);
        self::assertInstanceOf(Identity\Numeric\IntegerStorage::class, $table->columns[2]->type->identity);
        self::assertSame(Identity\BuiltinIdentity::TinyInt, $table->columns[2]->type->identity->base);
        self::assertSame('tinyint', $table->columns[2]->type->name);
        $boolean = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(c BOOLEAN)')->tables[0];
        self::assertSame(Identity\BuiltinIdentity::Boolean, $boolean->columns[0]->type->identity);
    }

    public function testNamedRetainsQuotedCaseAndAttributeParts(): void
    {
        $column = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a "App"."Measure")')->tables[0]->columns[0];
        $identity = $column->type->identity;
        self::assertInstanceOf(Identity\NamedIdentity::class, $identity);
        self::assertSame(['App', 'Measure'], $identity->reference->parts);
        self::assertSame('App.Measure', $column->type->name);
    }
}
