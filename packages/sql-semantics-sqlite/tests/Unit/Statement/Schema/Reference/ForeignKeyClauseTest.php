<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DecoratedColumnName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ForeignKeyClause;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceAction;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceEvent;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceReaction;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ForeignKeyClause::class)]
#[Medium]
final class ForeignKeyClauseTest extends TestCase
{
    public function testReactionIsThatOfTheLastActionWrittenForTheEvent(): void
    {
        $clause = new ForeignKeyClause(new Name('parent'), null, [
            new ReferenceAction(ReferenceEvent::Delete, ReferenceReaction::Cascade),
            new ReferenceAction(ReferenceEvent::Update, ReferenceReaction::SetNull),
            new ReferenceAction(ReferenceEvent::Delete, ReferenceReaction::Restrict),
        ]);

        self::assertSame(ReferenceReaction::Restrict, $clause->reaction(ReferenceEvent::Delete));
        self::assertSame(ReferenceReaction::SetNull, $clause->reaction(ReferenceEvent::Update));
    }

    public function testReactionIsNoActionWhenNoneIsWritten(): void
    {
        self::assertSame(ReferenceReaction::NoAction, (new ForeignKeyClause(new Name('parent')))->reaction(ReferenceEvent::Delete));
    }

    public function testDeriveConstraintDoesNotLookTheParentUp(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES missing (id))', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveConstraintReportsADecoratedParentColumn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent (id COLLATE nocase))');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(DecoratedColumnName::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderWritesTheParentTheColumnsAndTheClauses(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table c (p references parent(a, b) on delete set default match full)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $clause = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(ForeignKeyClause::class, $clause);
        self::assertSame('parent', $clause->table->value);
        self::assertCount(2, $clause->columns ?? []);
        self::assertSame('CREATE TABLE c (p REFERENCES parent (a, b) ON DELETE SET DEFAULT MATCH `full`)', $operation->toString());
    }

    public function testRenderKeepsAnAbsentColumnListAbsent(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $clause = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(ForeignKeyClause::class, $clause);
        self::assertNull($clause->columns);
        self::assertSame([], $clause->arguments);
    }
}
