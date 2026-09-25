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
use SqlSemantics\Model\Scalar\Control\RaiseAction;
use SqlSemantics\Model\Scalar\Control\RaiseError;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(RaiseError::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RaiseErrorTest extends TestCase
{
    public function testInputsContainOnlyTheMessage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(ABORT, 'stop'); END");
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseError::class, $raise);
        self::assertSame([$raise->message], $raise->inputs());
        self::assertSame("'stop'", $raise->message->spelling());
        self::assertSame(ExpressionKind::Raise, $raise->kind);
        self::assertSame('never', $raise->type->name);
    }

    public function testSpellingNamesTheRequestedEffect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(FAIL, 'stop'); END");
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseError::class, $raise);
        self::assertSame(RaiseAction::Fail, $raise->action);
        self::assertSame('RAISE FAIL', $raise->spelling());
        self::assertSame("RAISE(FAIL, 'stop')", $raise->structure()->toString());
    }

    public function testWithFactsKeepsTheActionAndMessage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(ROLLBACK, 'stop'); END");
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseError::class, $raise);
        $changed = $raise->withFacts(new ExpressionFacts($raise->type, Nullability::Unknown));
        self::assertNotSame($raise, $changed);
        self::assertSame(Nullability::Unknown, $changed->nullability);
        self::assertSame(Nullability::NotNull, $raise->nullability);
        self::assertSame(RaiseAction::Rollback, $changed->action);
        self::assertSame($raise->message, $changed->message);
    }

    public function testRejectsFactsOutsideSqlite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(ABORT, 'stop'); END");
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseError::class, $raise);
        $foreign = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new RaiseError($foreign->facts, $raise->source, $raise->action, $raise->message);
    }

    public function testRejectsAMessageFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(ABORT, 'stop'); END");
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $step = $statement->body->steps[0];
        self::assertInstanceOf(BoundSelect::class, $step);
        $raise = $step->outputs[0]->expression;
        self::assertInstanceOf(RaiseError::class, $raise);
        $this->expectException(InvalidStructure::class);
        new RaiseError($raise->facts, $raise->source, $raise->action, Expression::literal('stop', Dialect::MySql));
    }

    public function testSerializesInsideTheTriggerBody(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind("CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT RAISE(ABORT, 'stop'); END");
        self::assertSame('CREATE TRIGGER "tr" BEFORE INSERT ON "main"."t" FOR EACH ROW BEGIN SELECT RAISE(ABORT, \'stop\'); END', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
