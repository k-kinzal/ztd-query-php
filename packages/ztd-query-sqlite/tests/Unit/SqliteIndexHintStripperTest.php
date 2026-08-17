<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\SqliteIndexHintStripper;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqliteIndexHintStripper::class)]
#[UsesClass(SqlTokenStream::class)]
final class SqliteIndexHintStripperTest extends TestCase
{
    public function testStripsIndexedByFromShadowSource(): void
    {
        self::assertSame(
            'SELECT * FROM products  WHERE category = ?',
            (new SqliteIndexHintStripper())->strip(
                'SELECT * FROM products INDEXED BY idx_category WHERE category = ?',
                ['products'],
            ),
        );
    }

    public function testStripsHintsAfterAliasesAndInNestedScopes(): void
    {
        self::assertSame(
            'SELECT * FROM products AS p  WHERE EXISTS (SELECT 1 FROM products nested )',
            (new SqliteIndexHintStripper())->strip(
                'SELECT * FROM products AS p INDEXED BY [idx_category] WHERE EXISTS (SELECT 1 FROM products nested NOT INDEXED)',
                ['products'],
            ),
        );
    }

    public function testPreservesHintsForPhysicalSources(): void
    {
        $sql = 'SELECT * FROM products INDEXED BY idx_category JOIN audit NOT INDEXED ON audit.id = products.id';

        self::assertSame($sql, (new SqliteIndexHintStripper())->strip($sql, ['users']));
    }

    public function testDoesNotTreatExpressionsAsSourceHints(): void
    {
        $sql = "SELECT 'INDEXED BY idx_category' FROM products WHERE note = 'NOT INDEXED'";

        self::assertSame($sql, (new SqliteIndexHintStripper())->strip($sql, ['products']));
    }
}
