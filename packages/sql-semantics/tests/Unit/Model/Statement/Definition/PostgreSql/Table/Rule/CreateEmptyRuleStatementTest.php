<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Table\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Rule\RuleEvent;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule\CreateEmptyRuleStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateEmptyRuleStatement::class)]
#[Medium]
final class CreateEmptyRuleStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE OR REPLACE RULE r AS ON UPDATE TO t WHERE NEW.a > OLD.a DO INSTEAD NOTHING');
        self::assertInstanceOf(CreateEmptyRuleStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Create, $copy->kind);
    }

    public function testWithNameLeavesTheOriginalUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON UPDATE TO t DO NOTHING');
        self::assertInstanceOf(CreateEmptyRuleStatement::class, $statement);
        self::assertSame('s', $statement->withName('s')->name);
        self::assertSame('r', $statement->name);
    }

    public function testWithEventRejectsSelect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON UPDATE TO t DO NOTHING');
        self::assertInstanceOf(CreateEmptyRuleStatement::class, $statement);
        self::assertSame(RuleEvent::Delete, $statement->withEvent(RuleEvent::Delete)->event);
        $this->expectException(InvalidStructure::class);
        $statement->withEvent(RuleEvent::Select);
    }

    public function testWithInsteadSuppressesTheCommand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON UPDATE TO t DO NOTHING');
        self::assertInstanceOf(CreateEmptyRuleStatement::class, $statement);
        self::assertStringEndsWith('DO INSTEAD NOTHING', $statement->withInstead(true)->toString());
    }

    public function testWithConditionRemovesTheCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON UPDATE TO t WHERE OLD.a > 0 DO NOTHING');
        self::assertInstanceOf(CreateEmptyRuleStatement::class, $statement);
        self::assertNull($statement->withCondition(null)->condition);
    }

    public function testWithOrReplaceChoosesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON UPDATE TO t DO NOTHING');
        self::assertInstanceOf(CreateEmptyRuleStatement::class, $statement);
        self::assertStringStartsWith('CREATE OR REPLACE RULE', $statement->withOrReplace(true)->toString());
    }
}
