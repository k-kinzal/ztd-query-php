<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\ProposedRow;
use SqlSemantics\Model\Statement\Insert\InsertValuesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProposedRow::class)]
#[Medium]
final class ProposedRowTest extends TestCase
{
    public function testSharesTheTargetDeclarationUnderTheExcludedAlias(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(id) VALUES (1) ON CONFLICT (id) DO UPDATE SET x=excluded.id');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $target = $statement->insertion->target;
        $row = new ProposedRow('r9', $statement->scopeId, $target->declaration, 'excluded', $target->source, $target);
        self::assertSame('excluded', $row->alias);
        self::assertSame('r9', $row->id);
        self::assertSame($target, $row->target);
        self::assertSame($target->declaration, $row->declaration);
        self::assertSame(['id', 'x'], array_column($row->declaration->columns, 'name'));
    }

    public function testResultExpressionsAreEmptyForAProposedRow(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(id) VALUES (1)');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $target = $statement->insertion->target;
        $row = new ProposedRow('r9', $statement->scopeId, $target->declaration, 'excluded', $target->source, $target);
        self::assertSame([], $row->resultExpressions());
        self::assertSame([], $row->withScope('s9')->resultExpressions());
    }

    public function testWithScopeRetainsTheTargetAndAlias(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(id) VALUES (1)');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $target = $statement->insertion->target;
        $row = new ProposedRow('r9', $statement->scopeId, $target->declaration, 'excluded', $target->source, $target);
        $moved = $row->withScope('s9');
        self::assertNotSame($row, $moved);
        self::assertSame('s9', $moved->scopeId);
        self::assertSame($statement->scopeId, $row->scopeId);
        self::assertSame($target, $moved->target);
        self::assertSame('r9', $moved->id);
        self::assertSame('excluded', $moved->alias);
        self::assertSame($target->source, $moved->source);
    }

    public function testRejectsAMissingOccurrenceIdentity(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('INSERT INTO t(id) VALUES (1)');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        $target = $statement->insertion->target;
        $this->expectException(InvalidStructure::class);
        new ProposedRow('', $statement->scopeId, $target->declaration, 'excluded', $target->source, $target);
    }
}
