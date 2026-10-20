<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Platform\PostgreSql\Schema\CatalogColumn;
use SqlFixture\Platform\PostgreSql\Schema\TableDefinition;
use SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration as Subject;
use SqlFixture\Schema\TypeShape;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition as WrittenColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use Tests\Statement\PostgreSqlStatements;

#[CoversClass(Subject::class)]
#[UsesClass(CreateTableOperation::class)]
#[UsesClass(CatalogColumn::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TypeShape::class)]
final class TypeDeclarationTest extends TestCase
{
    #[DataProvider('providerTypeNames')]
    public function testShapeNamesTheTypeAfterItsCatalogEntry(string $declaration, string $expected): void
    {
        self::assertSame($expected, PostgreSqlStatements::shapes("c {$declaration}")[0]->type);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerTypeNames(): array
    {
        return [
            ['integer', 'INTEGER'],
            ['INT', 'INTEGER'],
            ['int4', 'INTEGER'],
            ['pg_catalog.int4', 'INTEGER'],
            ['SMALLINT', 'SMALLINT'],
            ['BIGINT', 'BIGINT'],
            ['DOUBLE PRECISION', 'DOUBLE PRECISION'],
            ['FLOAT', 'DOUBLE PRECISION'],
            ['REAL', 'REAL'],
            ['TIMESTAMP(3) WITH TIME ZONE', 'TIMESTAMPTZ'],
            ['TIMESTAMP', 'TIMESTAMP'],
            ['TIME WITH TIME ZONE', 'TIMETZ'],
            ['CHARACTER VARYING(100)', 'VARCHAR'],
            ['CHAR(2)', 'CHAR'],
            ['NUMERIC(10, 2)', 'NUMERIC'],
            ['DEC(5)', 'NUMERIC'],
            ['BIT VARYING(3)', 'VARBIT'],
            ['BOOLEAN', 'BOOLEAN'],
            ['TEXT[]', 'TEXT_ARRAY'],
            ['INT ARRAY', 'INTEGER_ARRAY'],
            ['timestamptz', 'TIMESTAMPTZ'],
            ['UUID', 'UUID'],
            ['JSONB', 'JSONB'],
            ['INTERVAL', 'INTERVAL'],
            ['INTERVAL(3)', 'INTERVAL'],
            ['INTERVAL DAY TO SECOND(2)', 'INTERVAL'],
            ['"_status"', '_STATUS'],
            ['my_enum', 'MY_ENUM'],
            ['app.my_enum', 'MY_ENUM'],
            ['app.my_enum[]', 'MY_ENUM_ARRAY'],
        ];
    }

    public function testShapeReadsModifiersArraysAndSerialTypes(): void
    {
        $shapes = PostgreSqlStatements::shapes('a VARCHAR(100), b NUMERIC(10, 2), c DEC(5), d TEXT[], e INTEGER[3][], g SERIAL, h BIGSERIAL, i SMALLSERIAL, j SERIAL4, k SERIAL8, l SERIAL2, m TIMESTAMP(3), n INT, o BINARY(16), p "serial", q "SERIAL", r INTERVAL(3), s VARCHAR(10)[], t NUMERIC(8, 2)[]');

        self::assertSame(['VARCHAR', 100, null, null], [$shapes[0]->type, $shapes[0]->length, $shapes[0]->precision, $shapes[0]->scale]);
        self::assertSame(['NUMERIC', null, 10, 2], [$shapes[1]->type, $shapes[1]->length, $shapes[1]->precision, $shapes[1]->scale]);
        self::assertSame([5, 0], [$shapes[2]->precision, $shapes[2]->scale]);
        self::assertSame('TEXT_ARRAY', $shapes[3]->type);
        self::assertSame(['INTEGER_ARRAY', null], [$shapes[4]->type, $shapes[4]->length]);
        self::assertSame(['INTEGER', true], [$shapes[5]->type, $shapes[5]->autoIncrement]);
        self::assertSame(['BIGINT', true], [$shapes[6]->type, $shapes[6]->autoIncrement]);
        self::assertSame(['SMALLINT', true], [$shapes[7]->type, $shapes[7]->autoIncrement]);
        self::assertSame(['INTEGER', 'BIGINT', 'SMALLINT'], [$shapes[8]->type, $shapes[9]->type, $shapes[10]->type]);
        self::assertSame(['TIMESTAMP', 3], [$shapes[11]->type, $shapes[11]->length]);
        self::assertSame(['INTEGER', null, false], [$shapes[12]->type, $shapes[12]->length, $shapes[12]->autoIncrement]);
        self::assertSame(['BINARY', 16], [$shapes[13]->type, $shapes[13]->length]);
        self::assertSame(['INTEGER', true], [$shapes[14]->type, $shapes[14]->autoIncrement]);
        self::assertSame(['SERIAL', false], [$shapes[15]->type, $shapes[15]->autoIncrement]);
        self::assertSame(['INTERVAL', 3], [$shapes[16]->type, $shapes[16]->length]);
        self::assertSame(['VARCHAR_ARRAY', 10], [$shapes[17]->type, $shapes[17]->length]);
        self::assertSame(['NUMERIC_ARRAY', 8, 2], [$shapes[18]->type, $shapes[18]->precision, $shapes[18]->scale]);
    }

    public function testNameNamesEveryDescriptorKind(): void
    {
        self::assertSame('INTEGER', (new Subject())->name(Builtin::Int4));
        self::assertSame('VARCHAR', (new Subject())->name(new Parameterized(Builtin::Varchar, 3)));
        self::assertSame('TEXT_ARRAY', (new Subject())->name(new ArrayOf(Builtin::Text)));
    }

    public function testWrittenModifiersReadsOnlyIntegerModifiersOfANamedType(): void
    {
        [, $statement] = PostgreSqlStatements::analyzed("CREATE TABLE t (a BINARY(16), b VARCHAR(3), c my_type('x'), d my_type)");
        $types = array_values(array_map(static fn (WrittenColumn $column) => $column->type, array_filter((new TableDefinition())->elements($statement), static fn (object $element): bool => $element instanceof WrittenColumn)));

        self::assertSame([16], (new Subject())->writtenModifiers($types[0]));
        self::assertSame([], (new Subject())->writtenModifiers($types[1]));
        self::assertSame([], (new Subject())->writtenModifiers($types[2]));
        self::assertSame([], (new Subject())->writtenModifiers($types[3]));
    }

    public function testSerialRecognizesOnlyAnUnqualifiedUnquotedSerialType(): void
    {
        [, $statement] = PostgreSqlStatements::analyzed('CREATE TABLE t (a serial, b BIGSERIAL, c "serial", d public.serial, e INT, f SERIAL[])');
        $serial = array_values(array_map(static fn (WrittenColumn $column): bool => (new Subject())->serial($column->type), array_filter((new TableDefinition())->elements($statement), static fn (object $element): bool => $element instanceof WrittenColumn)));

        self::assertSame([true, true, true, false, false, false], $serial);
    }

    public function testCatalogTypeNamesBuiltInTypesAndKeepsOtherNames(): void
    {
        self::assertSame('INTEGER', (new Subject())->catalogType('int4'));
        self::assertSame('CHAR', (new Subject())->catalogType('bpchar'));
        self::assertSame('_STATUS', (new Subject())->catalogType('_status'));
        self::assertSame('INT4', (new Subject())->catalogType('INT4'));
    }
}
