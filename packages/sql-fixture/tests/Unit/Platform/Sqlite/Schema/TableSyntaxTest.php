<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\TableSyntax as Subject;

#[CoversClass(Subject::class)]
final class TableSyntaxTest extends TestCase
{
    public function testNormalizeSqlRemovesCommentsAndWhitespace(): void
    {
        self::assertSame('CREATE TABLE a (id INT)', (new Subject())->normalizeSql('-- comment
        CREATE  TABLE a /* note */ (id INT)'));
    }

    public function testExtractTableNameReadsQualifiedNames(): void
    {
        self::assertSame('users', (new Subject())->extractTableName('CREATE TABLE IF NOT EXISTS "app"."users" (id INT)'));
        self::assertNull((new Subject())->extractTableName('SELECT 1'));
    }

    public function testExtractColumnsBlockPreservesNestedTypeArguments(): void
    {
        self::assertSame('amount NUMERIC(8, 2), id INT', (new Subject())->extractColumnsBlock('CREATE TABLE a (amount NUMERIC(8, 2), id INT)'));
        self::assertNull((new Subject())->extractColumnsBlock('SELECT 1'));
    }

    public function testExtractTablePrimaryKeysKeepsCompositeOrder(): void
    {
        self::assertSame(['tenant', 'id'], (new Subject())->extractTablePrimaryKeys('tenant INT, id INT, PRIMARY KEY ("tenant", "id")'));
        self::assertSame([], (new Subject())->extractTablePrimaryKeys('id INT'));
    }
}
