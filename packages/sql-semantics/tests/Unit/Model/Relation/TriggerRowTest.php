<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TriggerRow;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TriggerRow::class)]
#[Medium]
final class TriggerRowTest extends TestCase
{
    #[TestWith([RowVersion::Old, 'old'])]
    #[TestWith([RowVersion::New, 'new'])]
    public function testNamesTheRowImageAfterItsVersion(RowVersion $version, string $alias): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $subject = $statement->subject;
        $row = new TriggerRow('r9', $subject->scopeId, $subject->declaration, $subject->source, $version);
        self::assertSame($alias, $row->alias);
        self::assertSame($version, $row->version);
        self::assertSame('r9', $row->id);
        self::assertSame($subject->declaration, $row->declaration);
        self::assertSame(['id', 'x'], array_column($row->declaration->columns, 'name'));
    }

    public function testResultExpressionsAreEmptyForARowImage(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER INSERT ON t BEGIN INSERT INTO t(id) VALUES (new.id); END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $subject = $statement->subject;
        $row = new TriggerRow('r9', $subject->scopeId, $subject->declaration, $subject->source, RowVersion::New);
        self::assertSame([], $row->resultExpressions());
        self::assertSame([], $row->withScope('s9')->resultExpressions());
    }

    public function testWithScopeRetainsTheVersionAndDeclaration(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER DELETE ON t BEGIN DELETE FROM t WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $subject = $statement->subject;
        $row = new TriggerRow('r9', $subject->scopeId, $subject->declaration, $subject->source, RowVersion::Old);
        $moved = $row->withScope('s9');
        self::assertNotSame($row, $moved);
        self::assertSame('s9', $moved->scopeId);
        self::assertSame($subject->scopeId, $row->scopeId);
        self::assertSame(RowVersion::Old, $moved->version);
        self::assertSame('old', $moved->alias);
        self::assertSame('r9', $moved->id);
        self::assertSame($subject->declaration, $moved->declaration);
        self::assertSame($subject->source, $moved->source);
    }

    public function testRejectsAMissingScopeIdentity(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER DELETE ON t BEGIN DELETE FROM t WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $subject = $statement->subject;
        $this->expectException(InvalidStructure::class);
        new TriggerRow('r9', '', $subject->declaration, $subject->source, RowVersion::New);
    }
}
