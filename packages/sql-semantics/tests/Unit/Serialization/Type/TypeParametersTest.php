<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Type\TypeParameters;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

#[CoversClass(TypeParameters::class)]
#[Medium]
final class TypeParametersTest extends TestCase
{
    public function testNumbersOmitsAbsentParameters(): void
    {
        self::assertSame('', TypeParameters::numbers([])->toString());
        self::assertSame('', TypeParameters::numbers([null, null])->toString());
        self::assertSame('(10)', TypeParameters::numbers([new NumericParameter('10'), null])->toString());
        self::assertSame('(10, 2)', TypeParameters::numbers([new NumericParameter('10'), new NumericParameter('2')])->toString());
    }

    public function testIntegerWritesWidthAndSignedness(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT(11) UNSIGNED, b BIGINT)');
        $unsigned = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\IntegerStorage::class, $unsigned);
        self::assertSame('integer(11) UNSIGNED', TypeParameters::integer($unsigned)->toString());
        $plain = $schema->tables[0]->columns[1]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\IntegerStorage::class, $plain);
        self::assertSame('bigint', TypeParameters::integer($plain)->toString());
    }

    public function testNumericWritesPrecisionScaleAndSignedness(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a DECIMAL(10,2) UNSIGNED, b DECIMAL)');
        $scaled = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\NumericStorage::class, $scaled);
        self::assertSame('numeric(10, 2) UNSIGNED', TypeParameters::numeric($scaled)->toString());
        $plain = $schema->tables[0]->columns[1]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\NumericStorage::class, $plain);
        self::assertSame('numeric', TypeParameters::numeric($plain)->toString());
    }

    public function testStringWritesLengthEncodingAndBinary(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a VARCHAR(10) CHARACTER SET latin1 BINARY, b NATIONAL VARCHAR(10))');
        $encoded = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\StringStorage::class, $encoded);
        self::assertSame('varchar(10) CHARACTER SET `latin1` BINARY', TypeParameters::string($encoded, Dialect::MySql)->toString());
        $national = $schema->tables[0]->columns[1]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\StringStorage::class, $national);
        self::assertSame('NATIONAL varchar(10)', TypeParameters::string($national, Dialect::MySql)->toString());
    }

    public function testTemporalWritesPrecisionBeforeTheTimeZone(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TIMESTAMP(3) WITH TIME ZONE, b TIME WITHOUT TIME ZONE, c timestamptz(-3))');
        $zoned = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\TemporalStorage::class, $zoned);
        self::assertSame('timestamp(3) WITH TIME ZONE', TypeParameters::temporal($zoned)->toString());
        $local = $schema->tables[0]->columns[1]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\TemporalStorage::class, $local);
        self::assertSame('time WITHOUT TIME ZONE', TypeParameters::temporal($local)->toString());
        $signed = $schema->tables[0]->columns[2]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\TemporalStorage::class, $signed);
        self::assertSame('timestamptz(- (3))', TypeParameters::temporal($signed)->toString());
    }

    public function testIntervalWritesFieldsAndPrecision(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTERVAL DAY TO SECOND(3), b INTERVAL)');
        $ranged = $schema->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\IntervalStorage::class, $ranged);
        self::assertSame('INTERVAL DAY TO SECOND(3)', TypeParameters::interval($ranged)->toString());
        $plain = $schema->tables[0]->columns[1]->type->identity;
        self::assertInstanceOf(\SqlSemantics\Type\Identity\IntervalStorage::class, $plain);
        self::assertSame('INTERVAL', TypeParameters::interval($plain)->toString());
    }
}
