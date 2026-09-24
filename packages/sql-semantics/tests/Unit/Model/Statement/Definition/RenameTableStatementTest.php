<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\RenameTableStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameTableStatement::class)]
#[Medium]
final class RenameTableStatementTest extends TestCase
{
    public function testBindsTheQualifiedTableAndItsNewName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE main.t RENAME TO u');
        self::assertInstanceOf(RenameTableStatement::class, $statement);
        self::assertSame(['main', 't'], $statement->table->parts);
        self::assertSame('u', $statement->newName);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertSame('ALTER TABLE "main"."t" RENAME TO "u"', $statement->toString());
    }

    public function testWithOriginPreservesBothNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t RENAME TO u');
        self::assertInstanceOf(RenameTableStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->table, $copy->table);
        self::assertSame('u', $copy->newName);
        self::assertSame('ALTER TABLE "t" RENAME TO "u"', $copy->toString());
    }
}
