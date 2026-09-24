<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\RenameColumnStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameColumnStatement::class)]
#[Medium]
final class RenameColumnStatementTest extends TestCase
{
    public function testBindsTheOldAndNewColumnNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE main.t RENAME COLUMN n TO m');
        self::assertInstanceOf(RenameColumnStatement::class, $statement);
        self::assertSame(['main', 't'], $statement->table->parts);
        self::assertSame('n', $statement->column);
        self::assertSame('m', $statement->newName);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertSame('ALTER TABLE "main"."t" RENAME COLUMN "n" TO "m"', $statement->toString());
    }

    public function testWithOriginPreservesBothNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t RENAME COLUMN n TO m');
        self::assertInstanceOf(RenameColumnStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->table, $copy->table);
        self::assertSame('n', $copy->column);
        self::assertSame('m', $copy->newName);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
