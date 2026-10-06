<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\TypeDeclaration as Subject;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
final class TypeDeclarationTest extends TestCase
{
    #[DataProvider('providerTypeNames')]
    public function testTypeNameJoinsTheDeclaredWords(string $declaration, string $expected): void
    {
        $tree = (new SqliteParser())->parse("CREATE TABLE t (c {$declaration})");

        self::assertSame($expected, (new Subject())->typeName($tree->find('typetoken')[0]));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerTypeNames(): array
    {
        return [
            ['', 'BLOB'],
            ['integer', 'INTEGER'],
            ['VARCHAR(100)', 'VARCHAR'],
            ['DECIMAL(10, 2)', 'DECIMAL'],
            ['UNSIGNED BIG INT', 'UNSIGNED BIG INT'],
            ['DOUBLE PRECISION', 'DOUBLE PRECISION'],
            ['TEXT GENERATED ALWAYS AS (1)', 'TEXT'],
            ['GENERATED ALWAYS AS (1)', 'BLOB'],
        ];
    }

    public function testParseReadsLengthOrPrecisionAndScale(): void
    {
        $tree = (new SqliteParser())->parse('CREATE TABLE t (a VARCHAR(50), b DECIMAL(10, 2), c INT, d NUMERIC(+5, -1))');
        $shapes = array_map(static fn ($type): \SqlFixture\Schema\TypeShape => (new Subject())->parse($type), $tree->find('typetoken'));

        self::assertSame(['VARCHAR', 50, null, null], [$shapes[0]->type, $shapes[0]->length, $shapes[0]->precision, $shapes[0]->scale]);
        self::assertSame(['DECIMAL', null, 10, 2], [$shapes[1]->type, $shapes[1]->length, $shapes[1]->precision, $shapes[1]->scale]);
        self::assertSame(['INT', null, null, null], [$shapes[2]->type, $shapes[2]->length, $shapes[2]->precision, $shapes[2]->scale]);
        self::assertSame([5, 1], [$shapes[3]->precision, $shapes[3]->scale]);
        self::assertFalse($shapes[0]->autoIncrement);
    }
}
