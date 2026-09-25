<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Table\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Rule\RuleEvent;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule\CreateCommandRuleStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateCommandRuleStatement::class)]
#[Medium]
final class CreateCommandRuleStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON UPDATE TO t DO (NOTIFY ch; DELETE FROM t WHERE a = OLD.a)');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
        self::assertSame('CREATE RULE "r" AS ON UPDATE TO "public"."t" DO ALSO(NOTIFY "ch"; DELETE FROM "public"."t" WHERE ("a" = "old"."a"))', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithNameLeavesTheOriginalUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO NOTIFY ch');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        self::assertSame('s', $statement->withName('s')->name);
        self::assertSame('r', $statement->name);
    }

    public function testWithEventRebindsTheRowImages(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO NOTIFY ch');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        self::assertSame(RuleEvent::Delete, $statement->withEvent(RuleEvent::Delete)->event);
        $this->expectException(InvalidStructure::class);
        $statement->withEvent(RuleEvent::Select);
    }

    public function testWithActionsRequiresAnAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO (NOTIFY a; NOTIFY b)');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        self::assertCount(1, $statement->withActions([$statement->actions[1]])->actions);
        $this->expectException(InvalidStructure::class);
        $statement->withActions([]);
    }

    public function testWithInsteadReplacesTheCommand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO NOTIFY ch');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        self::assertTrue($statement->withInstead(true)->instead);
        self::assertFalse($statement->instead);
    }

    public function testWithConditionRejectsAConditionalNotification(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t WHERE NEW.a > 0 DO SELECT 1');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        self::assertNull($statement->withCondition(null)->condition);
        $notify = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO NOTIFY ch');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $notify);
        $this->expectException(InvalidStructure::class);
        $notify->withCondition($statement->condition);
    }

    public function testWithOrReplaceChoosesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO NOTIFY ch');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $statement);
        self::assertTrue($statement->withOrReplace(true)->orReplace);
    }
}
