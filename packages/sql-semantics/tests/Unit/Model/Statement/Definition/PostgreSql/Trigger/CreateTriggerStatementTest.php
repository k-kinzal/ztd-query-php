<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\TransitionTable;
use SqlSemantics\Model\Definition\Trigger\TriggerEvent;
use SqlSemantics\Model\Definition\Trigger\TriggerEvents;
use SqlSemantics\Model\Definition\Trigger\TriggerInvocation;
use SqlSemantics\Model\Definition\Trigger\TriggerLevel;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateTriggerStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateTriggerStatement::class)]
#[Medium]
final class CreateTriggerStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t REFERENCING OLD TABLE AS gone FOR EACH STATEMENT EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Create, $copy->kind);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameLeavesTheOriginalUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        $changed = $statement->withName('x"y');
        self::assertSame('audit', $statement->name);
        self::assertSame('CREATE TRIGGER "x""y" AFTER UPDATE ON "public"."t" FOR EACH STATEMENT EXECUTE FUNCTION "f"()', $changed->toString());
    }

    public function testWithTimingRejectsAnInsteadOfStatementTrigger(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame(Timing::Before, $statement->withTiming(Timing::Before)->timing);
        $this->expectException(InvalidStructure::class);
        $statement->withTiming(Timing::InsteadOf);
    }

    public function testWithEventsWritesTheColumnListAfterUpdate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        $changed = $statement->withEvents(new TriggerEvents([TriggerEvent::Delete, TriggerEvent::Update], ['a']));
        self::assertSame('CREATE TRIGGER "audit" AFTER DELETE OR UPDATE OF "a" ON "public"."t" FOR EACH STATEMENT EXECUTE FUNCTION "f"()', $changed->toString());
        self::assertSame([TriggerEvent::Update], $statement->events->events);
    }

    public function testWithLevelRebindsTheGranularity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame(TriggerLevel::Row, $statement->withLevel(TriggerLevel::Row)->level);
        self::assertSame(TriggerLevel::Statement, $statement->level);
    }

    public function testWithInvocationWritesArgumentsAsStringConstants(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        $changed = $statement->withInvocation(new TriggerInvocation(new QualifiedName(['s', 'g']), ['it\'s', '2']));
        self::assertSame(['it\'s', '2'], $changed->invocation->arguments);
        self::assertStringEndsWith('EXECUTE FUNCTION "s"."g"(\'it\'\'s\', \'2\')', $changed->toString());
    }

    public function testWithTransitionsRequiresAnAvailableRowVersion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        $changed = $statement->withTransitions([new TransitionTable(RowVersion::New, 'added')]);
        self::assertStringContainsString('REFERENCING NEW TABLE AS "added"', $changed->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withTiming(Timing::Before)->withTransitions([new TransitionTable(RowVersion::New, 'added')]);
    }

    public function testWithConditionRemovesTheCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t FOR EACH ROW WHEN (NEW.a > 1) EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertNotNull($statement->condition);
        self::assertNull($statement->withCondition(null)->condition);
    }

    public function testWithOrReplaceChoosesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER audit AFTER UPDATE ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertStringStartsWith('CREATE OR REPLACE TRIGGER', $statement->withOrReplace(true)->toString());
        self::assertFalse($statement->orReplace);
    }
}
