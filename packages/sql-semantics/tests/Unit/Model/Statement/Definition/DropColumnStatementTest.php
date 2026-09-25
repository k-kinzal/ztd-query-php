<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\DropColumnStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropColumnStatement::class)]
#[Medium]
final class DropColumnStatementTest extends TestCase
{
    public function testBindsTheTargetColumnWithDefaultBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t DROP COLUMN n');
        self::assertInstanceOf(DropColumnStatement::class, $statement);
        self::assertSame(['t'], $statement->table->parts);
        self::assertSame('n', $statement->column);
        self::assertFalse($statement->ifExists);
        self::assertSame(DropBehavior::Default, $statement->behavior);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertSame('ALTER TABLE "t" DROP COLUMN "n"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginPreservesTheTargetAndBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t DROP COLUMN n');
        self::assertInstanceOf(DropColumnStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->table, $copy->table);
        self::assertSame('n', $copy->column);
        self::assertSame($statement->behavior, $copy->behavior);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }
}
