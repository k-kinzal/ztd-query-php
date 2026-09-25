<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build("CREATE TABLE t(a app.measure(currency, 'USD', 12), b pg_catalog.serial)")->tables[0];
        $measure = $table->columns[0]->type->identity;
        self::assertInstanceOf(Identity\NamedIdentity::class, $measure);
        self::assertSame(['app', 'measure'], $measure->reference->parts);
        self::assertCount(3, $measure->arguments);
        self::assertSame('app.measure', $table->columns[0]->type->name);
        $serial = $table->columns[1]->type->identity;
        self::assertInstanceOf(Identity\NamedIdentity::class, $serial);
        self::assertSame(['pg_catalog', 'serial'], $serial->reference->parts);
        self::assertSame([], $serial->arguments);
    }

    public function testReadBindsQuotedAndCatalogQualifiedTypeNamesToBuiltinTypes(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a "int4", b pg_catalog.int4, c pg_catalog."varchar"(3), d "timestamptz", e pg_catalog.int8[], f "float8", g pg_catalog.bool)')->tables[0];
        self::assertSame(['integer', 'integer', 'varchar', 'timestamptz', 'bigint[]', 'double precision', 'boolean'], array_map(static fn ($column): string => $column->type->name, $table->columns));
        self::assertInstanceOf(Identity\Numeric\IntegerStorage::class, $table->columns[1]->type->identity);
        self::assertInstanceOf(Identity\StringStorage::class, $table->columns[2]->type->identity);
        $length = $table->columns[2]->type->identity->length;
        self::assertInstanceOf(Identity\Numeric\NumericParameter::class, $length);
        self::assertSame('3', $length->spelling);
    }

    public function testReadKeepsQuotedKeywordSpellingsAndOtherSchemasAsNamedTypes(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a "integer", b "INT4", c public.int4, d "char")')->tables[0];
        self::assertSame([['integer'], ['INT4'], ['public', 'int4'], ['char']], array_map(static fn ($column): array => $column->type->identity instanceof Identity\NamedIdentity ? $column->type->identity->reference->parts : [], $table->columns));
    }

    public function testCatalogNameReadsUnqualifiedAndPgCatalogNamesOnly(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a pg_catalog.int4)')->tables[0];
        $generic = \SqlSemantics\Ast\Tree::outer($table->columns[0]->source, ['GenericType'])[0];
        self::assertSame('int4', StandardTypeReader::catalogName($generic));
        $other = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a app.int4)')->tables[0];
        self::assertNull(StandardTypeReader::catalogName(\SqlSemantics\Ast\Tree::outer($other->columns[0]->source, ['GenericType'])[0]));
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

    /**
     * @return iterable<string, array{string, class-string<Identity\TypeIdentity>, string}>
     */
    public static function providerReadClassifiesPostgresSpellings(): iterable
    {
        yield 'integer alias' => ['int4', Identity\Numeric\IntegerStorage::class, 'integer'];
        yield 'mixed case alias' => ['Int8', Identity\Numeric\IntegerStorage::class, 'bigint'];
        yield 'double alias' => ['float8', Identity\Numeric\NumericStorage::class, 'double precision'];
        yield 'zoned timestamp alias' => ['timestamptz', Identity\TemporalStorage::class, 'timestamptz'];
        yield 'zoned timestamp words' => ['timestamp(3) with time zone', Identity\TemporalStorage::class, 'timestamptz'];
        yield 'builtin without alias' => ['jsonb', Identity\BuiltinIdentity::class, 'jsonb'];
        yield 'user type' => ['mytype', Identity\NamedIdentity::class, 'mytype'];
        yield 'user type with operands' => ['mytype(5)', Identity\NamedIdentity::class, 'mytype'];
        yield 'qualified builtin name' => ['public.text', Identity\NamedIdentity::class, 'public.text'];
        yield 'quoted builtin name' => ['"integer"', Identity\NamedIdentity::class, 'integer'];
        yield 'unclassified catalog type' => ['money', Identity\NamedIdentity::class, 'money'];
        yield 'unclassified catalog type with operands' => ['money(3)', Identity\NamedIdentity::class, 'money'];
        yield 'blank-padded catalog name with a length' => ['bpchar(3)', Identity\StringStorage::class, 'char'];
        yield 'blank-padded catalog name of any length' => ['bpchar', Identity\StringStorage::class, 'bpchar'];
        yield 'two-byte integer catalog name' => ['int2', Identity\Numeric\IntegerStorage::class, 'smallint'];
        yield 'four-byte integer catalog name' => ['int4', Identity\Numeric\IntegerStorage::class, 'integer'];
        yield 'eight-byte integer catalog name' => ['int8', Identity\Numeric\IntegerStorage::class, 'bigint'];
        yield 'four-byte float catalog name' => ['float4', Identity\Numeric\NumericStorage::class, 'real'];
        yield 'eight-byte float catalog name' => ['float8', Identity\Numeric\NumericStorage::class, 'double precision'];
        yield 'boolean catalog name' => ['bool', Identity\BuiltinIdentity::class, 'boolean'];
        yield 'varying character catalog name' => ['varchar(4)', Identity\StringStorage::class, 'varchar'];
        yield 'zoned timestamp catalog name' => ['timestamptz(3)', Identity\TemporalStorage::class, 'timestamptz'];
        yield 'zoned time catalog name' => ['timetz', Identity\TemporalStorage::class, 'timetz'];
        yield 'serial2 column' => ['serial2', Identity\Numeric\IntegerStorage::class, 'smallint'];
        yield 'smallserial column' => ['smallserial', Identity\Numeric\IntegerStorage::class, 'smallint'];
        yield 'serial column' => ['serial', Identity\Numeric\IntegerStorage::class, 'integer'];
        yield 'serial4 column' => ['serial4', Identity\Numeric\IntegerStorage::class, 'integer'];
        yield 'quoted serial column' => ['"serial"', Identity\Numeric\IntegerStorage::class, 'integer'];
        yield 'serial8 column' => ['serial8', Identity\Numeric\IntegerStorage::class, 'bigint'];
        yield 'bigserial column' => ['bigserial', Identity\Numeric\IntegerStorage::class, 'bigint'];
        yield 'qualified serial name' => ['pg_catalog.serial', Identity\NamedIdentity::class, 'pg_catalog.serial'];
    }

    /**
     * @param class-string<Identity\TypeIdentity> $class
     */
    #[DataProvider('providerReadClassifiesPostgresSpellings')]
    public function testReadClassifiesPostgresSpellings(string $type, string $class, string $name): void
    {
        $column = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a ' . $type . ')')->tables[0]->columns[0];
        self::assertInstanceOf($class, $column->type->identity);
        self::assertSame($name, $column->type->name);
    }

    public function testReadRejectsModifiersABuiltinDoesNotTake(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a int4(5))');
    }

    public function testUserTypeNamesOnlyPostgreSqlNamesThatAreNoBuiltinSpelling(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a app.measure, b money, c int4)')->tables[0];
        $reader = new StandardTypeReader(new \SqlSemantics\Ast\TypeReader(Dialect::PostgreSql));
        $generics = array_map(static fn ($column): ?\SqlParser\Parser\Node => \SqlSemantics\Ast\Tree::outer($column->source, ['GenericType'])[0] ?? null, $table->columns);
        self::assertSame(['app', 'measure'], $reader->userType($generics[0])?->reference->parts);
        self::assertSame(['money'], $reader->userType($generics[1])?->reference->parts);
        self::assertNull($reader->userType($generics[2]));
        self::assertNull($reader->userType(null));
        self::assertNull((new StandardTypeReader(new \SqlSemantics\Ast\TypeReader(Dialect::MySql)))->userType($generics[0]));
    }
}
