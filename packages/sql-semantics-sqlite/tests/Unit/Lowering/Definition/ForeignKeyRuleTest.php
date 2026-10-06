<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\ForeignKeyRule;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\Deferrability;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ForeignKeyClause;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\InitialMode;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\MatchName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceAction;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceEvent;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceReaction;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\ForeignKey;

#[CoversClass(ForeignKeyRule::class)]
#[Medium]
final class ForeignKeyRuleTest extends TestCase
{
    public function testClauseLowersTheParentAndItsOptionalColumns(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (a REFERENCES p, b REFERENCES q (x, y))')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $first = $statement->columns[0]->constraints[0];
        $second = $statement->columns[1]->constraints[0];
        self::assertInstanceOf(ForeignKeyClause::class, $first);
        self::assertInstanceOf(ForeignKeyClause::class, $second);
        self::assertSame('p', $first->table->value);
        self::assertNull($first->columns);
        self::assertCount(2, $second->columns ?? []);
    }

    public function testArgumentsKeepTheirWrittenOrder(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (a REFERENCES p ON DELETE CASCADE MATCH full ON UPDATE NO ACTION ON INSERT RESTRICT)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $clause = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(ForeignKeyClause::class, $clause);
        self::assertCount(4, $clause->arguments);
        self::assertInstanceOf(MatchName::class, $clause->arguments[1]);
        self::assertSame(ReferenceReaction::Cascade, $clause->reaction(ReferenceEvent::Delete));
        self::assertSame(ReferenceReaction::NoAction, $clause->reaction(ReferenceEvent::Update));
    }

    public function testArgumentLowersEveryEventAndReaction(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (a REFERENCES p ON DELETE SET NULL ON UPDATE SET DEFAULT ON INSERT CASCADE ON DELETE RESTRICT ON UPDATE NO ACTION)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $clause = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(ForeignKeyClause::class, $clause);
        $pairs = array_map(static fn (object $argument): array => $argument instanceof ReferenceAction ? [$argument->event, $argument->reaction] : [], $clause->arguments);
        self::assertSame([
            [ReferenceEvent::Delete, ReferenceReaction::SetNull],
            [ReferenceEvent::Update, ReferenceReaction::SetDefault],
            [ReferenceEvent::Insert, ReferenceReaction::Cascade],
            [ReferenceEvent::Delete, ReferenceReaction::Restrict],
            [ReferenceEvent::Update, ReferenceReaction::NoAction],
        ], $pairs);
    }

    public function testDeferrabilityLowersBothForms(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (a REFERENCES p DEFERRABLE, b REFERENCES p NOT DEFERRABLE)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $plain = $statement->columns[0]->constraints[1];
        $negated = $statement->columns[1]->constraints[1];
        self::assertInstanceOf(Deferrability::class, $plain);
        self::assertInstanceOf(Deferrability::class, $negated);
        self::assertTrue($plain->deferrable);
        self::assertFalse($negated->deferrable);
    }

    public function testOptionalDeferrabilityIsNullWhenNoClauseIsWritten(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (a, FOREIGN KEY (a) REFERENCES p, FOREIGN KEY (a) REFERENCES p DEFERRABLE)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $without = $statement->constraints[0]->items[0];
        $with = $statement->constraints[1]->items[0];
        self::assertInstanceOf(ForeignKey::class, $without);
        self::assertInstanceOf(ForeignKey::class, $with);
        self::assertNull($without->deferrability);
        self::assertNotNull($with->deferrability);
    }

    public function testInitiallyLowersTheOptionalMode(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE c (a REFERENCES p DEFERRABLE, b REFERENCES p DEFERRABLE INITIALLY DEFERRED, d REFERENCES p DEFERRABLE INITIALLY IMMEDIATE)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $modes = array_map(static fn (object $column): ?InitialMode => $column->constraints[1] instanceof Deferrability ? $column->constraints[1]->initially : null, $statement->columns);
        self::assertSame([null, InitialMode::Deferred, InitialMode::Immediate], $modes);
    }
}
