<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Type\TypeFamilies;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity;

#[CoversClass(TypeFamilies::class)]
#[Medium]
final class TypeFamiliesTest extends TestCase
{
    public function testMakeBuildsIntegerAndNumericStorageWithTheUnsignedPolicy(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT(11) UNSIGNED, b DECIMAL(10, 2), c YEAR(4), d BIGINT)')->tables[0];
        $integer = $table->columns[0]->type->identity;
        self::assertInstanceOf(Identity\Numeric\IntegerStorage::class, $integer);
        self::assertSame(Identity\BuiltinIdentity::Integer, $integer->base);
        self::assertSame('11', $integer->displayWidth?->spelling);
        self::assertTrue($integer->unsigned);
        self::assertSame('integer unsigned', $table->columns[0]->type->name);
        $numeric = $table->columns[1]->type->identity;
        self::assertInstanceOf(Identity\Numeric\NumericStorage::class, $numeric);
        self::assertInstanceOf(Identity\Numeric\NumericParameter::class, $numeric->precision);
        self::assertSame('10', $numeric->precision->spelling);
        self::assertInstanceOf(Identity\Numeric\NumericParameter::class, $numeric->scale);
        self::assertSame('2', $numeric->scale->spelling);
        self::assertFalse($numeric->unsigned);
        $year = $table->columns[2]->type->identity;
        self::assertInstanceOf(Identity\Numeric\IntegerStorage::class, $year);
        self::assertSame(Identity\BuiltinIdentity::Year, $year->base);
        $bigint = $table->columns[3]->type->identity;
        self::assertInstanceOf(Identity\Numeric\IntegerStorage::class, $bigint);
        self::assertNull($bigint->displayWidth);
    }

    public function testMakeBuildsTemporalStorageWithTimeZoneModes(): void
    {
        $postgres = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TIME WITHOUT TIME ZONE, b TIMESTAMPTZ(3), c TIMESTAMP WITH TIME ZONE)')->tables[0];
        $time = $postgres->columns[0]->type->identity;
        self::assertInstanceOf(Identity\TemporalStorage::class, $time);
        self::assertSame(Identity\BuiltinIdentity::Time, $time->base);
        self::assertSame(Identity\TimeZoneMode::Without, $time->timeZone);
        $timestamptz = $postgres->columns[1]->type->identity;
        self::assertInstanceOf(Identity\TemporalStorage::class, $timestamptz);
        self::assertSame(Identity\BuiltinIdentity::Timestamp, $timestamptz->base);
        self::assertSame(Identity\TimeZoneMode::With, $timestamptz->timeZone);
        self::assertInstanceOf(Identity\Numeric\NumericParameter::class, $timestamptz->precision);
        self::assertSame('3', $timestamptz->precision->spelling);
        $explicit = $postgres->columns[2]->type->identity;
        self::assertInstanceOf(Identity\TemporalStorage::class, $explicit);
        self::assertSame(Identity\TimeZoneMode::With, $explicit->timeZone);
        $mysql = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a DATETIME(6))')->tables[0]->columns[0]->type->identity;
        self::assertInstanceOf(Identity\TemporalStorage::class, $mysql);
        self::assertSame(Identity\BuiltinIdentity::Datetime, $mysql->base);
        self::assertSame(Identity\TimeZoneMode::Unspecified, $mysql->timeZone);
    }

    public function testMakeBuildsStringStorageWithEncodingChoices(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a VARCHAR(20) CHARACTER SET utf8mb4, b NCHAR(3), c VARBINARY(8), d TEXT)')->tables[0];
        $varchar = $table->columns[0]->type->identity;
        self::assertInstanceOf(Identity\StringStorage::class, $varchar);
        self::assertSame(Identity\BuiltinIdentity::Varchar, $varchar->base);
        self::assertInstanceOf(Identity\Numeric\NumericParameter::class, $varchar->length);
        self::assertSame('20', $varchar->length->spelling);
        self::assertSame('utf8mb4', $varchar->characterSet);
        self::assertFalse($varchar->national);
        $national = $table->columns[1]->type->identity;
        self::assertInstanceOf(Identity\StringStorage::class, $national);
        self::assertSame(Identity\BuiltinIdentity::Char, $national->base);
        self::assertTrue($national->national);
        $binary = $table->columns[2]->type->identity;
        self::assertInstanceOf(Identity\StringStorage::class, $binary);
        self::assertSame(Identity\BuiltinIdentity::Varbinary, $binary->base);
        $text = $table->columns[3]->type->identity;
        self::assertInstanceOf(Identity\StringStorage::class, $text);
        self::assertNull($text->length);
    }

    public function testMakeReturnsTheBareIdentityForParameterlessFamilies(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a BOOLEAN, b DATE, c JSONB)')->tables[0];
        self::assertSame(Identity\BuiltinIdentity::Boolean, $table->columns[0]->type->identity);
        self::assertSame(Identity\BuiltinIdentity::Date, $table->columns[1]->type->identity);
        self::assertSame(Identity\BuiltinIdentity::Jsonb, $table->columns[2]->type->identity);
    }

    public function testMakeReadsBpcharAsCharOnlyWithALength(): void
    {
        $columns = (new SchemaBuilder(Dialect::PostgreSql, grammarVersion: 'pg-17.2'))->build('CREATE TABLE t(a bpchar, b bpchar(3), c char)')->tables[0]->columns;
        self::assertInstanceOf(Identity\StringStorage::class, $columns[0]->type->identity);
        self::assertSame(Identity\BuiltinIdentity::Bpchar, $columns[0]->type->identity->base);
        self::assertNull($columns[0]->type->identity->length);
        self::assertInstanceOf(Identity\StringStorage::class, $columns[1]->type->identity);
        self::assertSame(Identity\BuiltinIdentity::Char, $columns[1]->type->identity->base);
        self::assertInstanceOf(Identity\Numeric\NumericParameter::class, $columns[1]->type->identity->length);
        self::assertSame('3', $columns[1]->type->identity->length->spelling);
        self::assertSame('char', $columns[2]->type->name);
    }
}
