<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Control;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Control\RaiseIgnore;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(RaiseIgnore::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RaiseIgnoreTest extends TestCase
{
    public function testInputsAreEmpty(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(IGNORE); END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseIgnore::class, $raise);
        self::assertSame([], $raise->inputs());
        self::assertSame(ExpressionKind::Raise, $raise->kind);
        self::assertSame('never', $raise->type->name);
        self::assertSame(Nullability::NotNull, $raise->nullability);
    }

    public function testSpellingIsRaiseIgnore(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(IGNORE); END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseIgnore::class, $raise);
        self::assertSame('RAISE IGNORE', $raise->spelling());
        self::assertSame('RAISE(IGNORE)', $raise->structure()->toString());
    }

    public function testWithFactsReturnsANewIgnoreWithTheGivenFacts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(IGNORE); END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseIgnore::class, $raise);
        $changed = $raise->withFacts(new ExpressionFacts($raise->type, Nullability::Unknown));
        self::assertNotSame($raise, $changed);
        self::assertSame(Nullability::Unknown, $changed->nullability);
        self::assertSame(Nullability::NotNull, $raise->nullability);
        self::assertSame($raise->source, $changed->source);
    }

    public function testRejectsFactsOutsideSqlite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(IGNORE); END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseIgnore::class, $raise);
        $foreign = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new RaiseIgnore($foreign->facts, $raise->source);
    }

    public function testSerializesInsideTheTriggerBody(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(IGNORE); END');
        self::assertSame('CREATE TRIGGER "tr" BEFORE INSERT ON "main"."t" FOR EACH ROW BEGIN SELECT RAISE(IGNORE); END', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
