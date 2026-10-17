<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\Sqlite\Schema\TypeDeclaration as Subject;
use SqlFixture\Schema\TypeShape;
use Tests\Statement\SqliteStatements;

#[CoversClass(Subject::class)]
#[UsesClass(CreateTableOperation::class)]
#[UsesClass(NumericLiteral::class)]
#[UsesClass(TypeShape::class)]
final class TypeDeclarationTest extends TestCase
{
    #[DataProvider('providerTypeNames')]
    public function testShapeNamesTheDeclaredType(string $declaration, string $expected): void
    {
        self::assertSame($expected, SqliteStatements::shapes("c {$declaration}")[0]->type);
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
            ['"My Type"', 'MY TYPE'],
            ['TEXT GENERATED ALWAYS AS (1)', 'TEXT'],
            ['INT GENERATED  ALWAYS AS (1)', 'INT'],
            ['GENERATED ALWAYS AS (1)', 'BLOB'],
        ];
    }

    public function testShapeReadsLengthOrPrecisionAndScaleWithTheirSigns(): void
    {
        $shapes = SqliteStatements::shapes('a VARCHAR(50), b DECIMAL(10, 2), c INT, d NUMERIC(+5, -1), e CHAR(0x10), f REAL(3.9)');

        self::assertSame(['VARCHAR', 50, null, null], [$shapes[0]->type, $shapes[0]->length, $shapes[0]->precision, $shapes[0]->scale]);
        self::assertSame(['DECIMAL', null, 10, 2], [$shapes[1]->type, $shapes[1]->length, $shapes[1]->precision, $shapes[1]->scale]);
        self::assertSame(['INT', null, null, null], [$shapes[2]->type, $shapes[2]->length, $shapes[2]->precision, $shapes[2]->scale]);
        self::assertSame([5, -1], [$shapes[3]->precision, $shapes[3]->scale]);
        self::assertSame(16, $shapes[4]->length);
        self::assertSame(3, $shapes[5]->length);
        self::assertFalse($shapes[0]->autoIncrement);
    }

    public function testNumberReadsTheWholePartOfEachNumberKindWithItsSign(): void
    {
        [, $statement] = SqliteStatements::analyzed('CREATE TABLE t (a NUMERIC(-7, 0x1F), b NUMERIC(2.75, +.5), c DECIMAL(1e2, 2E1))');

        self::assertSame([-7, 31], array_map(static fn ($argument): int => (new Subject())->number($argument), $statement->columns[0]->type->arguments ?? []));
        self::assertSame([2, 0], array_map(static fn ($argument): int => (new Subject())->number($argument), $statement->columns[1]->type->arguments ?? []));
        self::assertSame([100, 20], array_map(static fn ($argument): int => (new Subject())->number($argument), $statement->columns[2]->type->arguments ?? []));
    }
}
