<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\SqliteAffinity;

#[CoversClass(SqliteAffinity::class)]
final class SqliteAffinityTest extends TestCase
{
    #[DataProvider('providerDeclaredType')]
    public function testOfAnswersTheAffinitySqliteFilesTheColumnUnder(
        string $declared,
        SqliteAffinity $affinity,
    ): void {
        self::assertSame($affinity, SqliteAffinity::of($declared));
    }

    /**
     * @return iterable<string, array{string, SqliteAffinity}>
     */
    public static function providerDeclaredType(): iterable
    {
        yield 'INT' => ['INT', SqliteAffinity::Integer];
        yield 'BIGINT' => ['BIGINT', SqliteAffinity::Integer];
        yield 'lowercase int' => ['integer', SqliteAffinity::Integer];
        yield 'VARCHAR' => ['VARCHAR(9)', SqliteAffinity::Text];
        yield 'CLOB' => ['CLOB', SqliteAffinity::Text];
        yield 'TEXT' => ['TEXT', SqliteAffinity::Text];
        yield 'BLOB' => ['BLOB', SqliteAffinity::Blob];
        yield 'no type at all' => ['', SqliteAffinity::Blob];
        yield 'REAL' => ['REAL', SqliteAffinity::Real];
        yield 'FLOAT' => ['FLOAT', SqliteAffinity::Real];
        yield 'DOUBLE' => ['DOUBLE', SqliteAffinity::Real];
        yield 'DECIMAL' => ['DECIMAL(10,2)', SqliteAffinity::Numeric];
        yield 'BOOLEAN' => ['BOOLEAN', SqliteAffinity::Numeric];
        yield 'a type sqlite has never heard of' => ['GEOMETRY', SqliteAffinity::Numeric];
    }

    public function testOfPrefersIntegerWhereATypeNameCouldMatchTwoRules(): void
    {
        self::assertSame(SqliteAffinity::Integer, SqliteAffinity::of('INTCHAR'));
    }

    public function testOfReadsTheTypeNameForSubstringsRatherThanRecognisingIt(): void
    {
        self::assertSame(SqliteAffinity::Integer, SqliteAffinity::of('POINT'));
    }
}
