<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration as Subject;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
final class TypeDeclarationTest extends TestCase
{
    public function testIsDecimalTypeRecognizesAliases(): void
    {
        self::assertTrue((new Subject())->isDecimalType('DEC'));
        self::assertTrue((new Subject())->isDecimalType('NUMERIC'));
        self::assertTrue((new Subject())->isDecimalType('DECIMAL'));
        self::assertFalse((new Subject())->isDecimalType('INTEGER'));
    }

    #[DataProvider('providerTypeNames')]
    public function testTypeNameReadsMultiWordAndQualifiedTypes(string $declaration, string $expected): void
    {
        $tree = (new PostgreSqlParser())->parse("CREATE TABLE t (c {$declaration})");

        self::assertSame($expected, (new Subject())->typeName($tree->find('Typename')[0]));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerTypeNames(): array
    {
        return [
            ['integer', 'INTEGER'],
            ['DOUBLE PRECISION', 'DOUBLE PRECISION'],
            ['TIMESTAMP(3) WITH TIME ZONE', 'TIMESTAMP WITH TIME ZONE'],
            ['TIME WITHOUT TIME ZONE', 'TIME WITHOUT TIME ZONE'],
            ['CHARACTER VARYING(100)', 'CHARACTER VARYING'],
            ['NUMERIC(10, 2)', 'NUMERIC'],
            ['BIT VARYING(3)', 'BIT VARYING'],
            ['pg_catalog.int4', 'INT4'],
            ['TEXT[]', 'TEXT'],
            ['timestamptz', 'TIMESTAMPTZ'],
            ['INTERVAL', 'INTERVAL'],
        ];
    }

    public function testParseReadsDimensionsArraysAndSerialTypes(): void
    {
        $sql = 'CREATE TABLE t (a VARCHAR(100), b NUMERIC(10, 2), c DEC(5), d TEXT[], e INTEGER[3][], f INT ARRAY, g SERIAL, h BIGSERIAL, i SMALLSERIAL, j SERIAL4, k SERIAL8, l SERIAL2, m TIMESTAMP(3), n INT)';
        $shapes = array_map(static fn ($type): \SqlFixture\Schema\TypeShape => (new Subject())->parse($type), (new PostgreSqlParser())->parse($sql)->find('Typename'));

        self::assertSame(['VARCHAR', 100, null, null], [$shapes[0]->type, $shapes[0]->length, $shapes[0]->precision, $shapes[0]->scale]);
        self::assertSame(['NUMERIC', null, 10, 2], [$shapes[1]->type, $shapes[1]->length, $shapes[1]->precision, $shapes[1]->scale]);
        self::assertSame(['DEC', 5, 0], [$shapes[2]->type, $shapes[2]->precision, $shapes[2]->scale]);
        self::assertSame('TEXT_ARRAY', $shapes[3]->type);
        self::assertSame(['INTEGER_ARRAY', null], [$shapes[4]->type, $shapes[4]->length]);
        self::assertSame('INT_ARRAY', $shapes[5]->type);
        self::assertSame(['INTEGER', true], [$shapes[6]->type, $shapes[6]->autoIncrement]);
        self::assertSame(['BIGINT', true], [$shapes[7]->type, $shapes[7]->autoIncrement]);
        self::assertSame(['SMALLINT', true], [$shapes[8]->type, $shapes[8]->autoIncrement]);
        self::assertSame(['INTEGER', 'BIGINT', 'SMALLINT'], [$shapes[9]->type, $shapes[10]->type, $shapes[11]->type]);
        self::assertSame(['TIMESTAMP', 3], [$shapes[12]->type, $shapes[12]->length]);
        self::assertSame(['INT', null, false], [$shapes[13]->type, $shapes[13]->length, $shapes[13]->autoIncrement]);
    }
}
