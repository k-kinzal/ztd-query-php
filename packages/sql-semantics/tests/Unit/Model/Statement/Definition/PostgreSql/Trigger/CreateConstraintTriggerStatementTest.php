<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\TriggerEvent;
use SqlSemantics\Model\Definition\Trigger\TriggerEvents;
use SqlSemantics\Model\Definition\Trigger\TriggerInvocation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateConstraintTriggerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateConstraintTriggerStatement::class)]
#[Medium]
final class CreateConstraintTriggerStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT); CREATE TABLE p(a INT)')))->bind('CREATE CONSTRAINT TRIGGER c AFTER INSERT ON t FROM p DEFERRABLE FOR EACH ROW EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateConstraintTriggerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(CheckingTime::DeferrableImmediate, $copy->checking);
    }

    public function testWithNameLeavesTheOriginalUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE CONSTRAINT TRIGGER c AFTER INSERT ON t FOR EACH ROW EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateConstraintTriggerStatement::class, $statement);
        self::assertSame('d', $statement->withName('d')->name);
        self::assertSame('c', $statement->name);
    }

    public function testWithEventsRejectsTruncate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE CONSTRAINT TRIGGER c AFTER INSERT ON t FOR EACH ROW EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateConstraintTriggerStatement::class, $statement);
        self::assertSame([TriggerEvent::Delete], $statement->withEvents(new TriggerEvents([TriggerEvent::Delete]))->events->events);
        $this->expectException(InvalidStructure::class);
        $statement->withEvents(new TriggerEvents([TriggerEvent::Truncate]));
    }

    public function testWithInvocationReplacesTheFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE CONSTRAINT TRIGGER c AFTER INSERT ON t FOR EACH ROW EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateConstraintTriggerStatement::class, $statement);
        self::assertSame(['g'], $statement->withInvocation(new TriggerInvocation(new QualifiedName(['g'])))->invocation->function->parts);
    }

    public function testWithCheckingWritesTheDeferral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE CONSTRAINT TRIGGER c AFTER INSERT ON t FOR EACH ROW EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateConstraintTriggerStatement::class, $statement);
        self::assertSame(CheckingTime::Immediate, $statement->checking);
        self::assertSame('CREATE CONSTRAINT TRIGGER "c" AFTER INSERT ON "public"."t" DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION "f"()', $statement->withChecking(CheckingTime::DeferrableDeferred)->toString());
    }

    public function testWithConditionRemovesTheCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE CONSTRAINT TRIGGER c AFTER INSERT ON t FOR EACH ROW WHEN (NEW.a IS NULL) EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateConstraintTriggerStatement::class, $statement);
        self::assertNotNull($statement->condition);
        self::assertNull($statement->withCondition(null)->condition);
    }
}
