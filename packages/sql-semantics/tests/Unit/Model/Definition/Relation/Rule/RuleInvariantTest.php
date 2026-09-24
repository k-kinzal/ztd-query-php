<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Rule\RuleEvent;
use SqlSemantics\Model\Definition\Relation\Rule\RuleInvariant;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule\CreateCommandRuleStatement;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RuleInvariant::class)]
#[Medium]
final class RuleInvariantTest extends TestCase
{
    #[TestWith([Dialect::Sqlite, 'r'])]
    #[TestWith([Dialect::PostgreSql, ''])]
    public function testIdentityRejectsAnotherLanguageOrAnEmptyName(Dialect $dialect, string $name): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RuleInvariant::identity($origin, $name, null);
    }

    #[TestWith(['_RETURN', true, true])]
    public function testActionsAcceptTheReplacedViewRule(string $name, bool $instead, bool $orReplace): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $query);
        self::assertSame([$query], RuleInvariant::actions($name, RuleEvent::Select, [$query], $instead, null, $orReplace));
    }

    #[TestWith(['view_rule', true, true])]
    #[TestWith(['_RETURN', false, true])]
    #[TestWith(['_RETURN', true, false])]
    public function testActionsRejectAnotherSelectRule(string $name, bool $instead, bool $orReplace): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        RuleInvariant::actions($name, RuleEvent::Select, [$query], $instead, null, $orReplace);
    }

    public function testActionsRejectAnEmptyList(): void
    {
        $this->expectException(InvalidStructure::class);
        RuleInvariant::actions('r', RuleEvent::Insert, [], false, null, false);
    }

    public function testActionsRejectReturningInAnAlsoRule(): void
    {
        $rule = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO INSTEAD INSERT INTO t VALUES (NEW.a) RETURNING a');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $rule);
        $this->expectException(InvalidStructure::class);
        RuleInvariant::actions('r', RuleEvent::Insert, $rule->actions, false, null, false);
    }

    public function testDialectRejectsAnActionOfAnotherLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        RuleInvariant::dialect((new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1'));
    }

    /**
     * @param list<RowVersion> $images
     */
    #[TestWith([RuleEvent::Select, []])]
    #[TestWith([RuleEvent::Insert, [RowVersion::New]])]
    #[TestWith([RuleEvent::Update, [RowVersion::Old, RowVersion::New]])]
    #[TestWith([RuleEvent::Delete, [RowVersion::Old]])]
    public function testImagesFollowTheEvent(RuleEvent $event, array $images): void
    {
        self::assertSame($images, RuleInvariant::images($event));
    }

    public function testIdentityAcceptsANamedPostgresRule(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT true');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        RuleInvariant::identity($query->origin, 'r', $query->outputs[0]->expression);
    }

    public function testIdentityRejectsAConditionOfAnotherLanguage(): void
    {
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $sqlite = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $sqlite);
        $this->expectException(InvalidStructure::class);
        RuleInvariant::identity($postgres->origin, 'r', $sqlite->outputs[0]->expression);
    }

    public function testActionsRejectAnActionOfAnotherLanguage(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        RuleInvariant::actions('r', RuleEvent::Insert, [$query], false, null, false);
    }

    public function testActionsRejectAConditionalNotify(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $notify = $binder->bind('NOTIFY c');
        $condition = $binder->bind('SELECT true');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Notification\NotifyStatement::class, $notify);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $condition);
        $this->expectException(InvalidStructure::class);
        RuleInvariant::actions('r', RuleEvent::Insert, [$notify], false, $condition->outputs[0]->expression, false);
    }

    public function testActionsAcceptAnUnconditionalNotify(): void
    {
        $notify = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('NOTIFY c');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Notification\NotifyStatement::class, $notify);
        self::assertSame([$notify], RuleInvariant::actions('r', RuleEvent::Insert, [$notify], false, null, false));
    }

    public function testActionsAcceptOneReturningActionOfAnUnconditionalInsteadRule(): void
    {
        $rule = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO INSTEAD INSERT INTO t VALUES (NEW.a) RETURNING a');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $rule);
        self::assertSame($rule->actions, RuleInvariant::actions('r', RuleEvent::Insert, $rule->actions, true, null, false));
    }

    public function testActionsRejectTwoReturningActions(): void
    {
        $rule = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE RULE r AS ON INSERT TO t DO INSTEAD INSERT INTO t VALUES (NEW.a) RETURNING a');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $rule);
        $this->expectException(InvalidStructure::class);
        RuleInvariant::actions('r', RuleEvent::Insert, [...$rule->actions, ...$rule->actions], true, null, false);
    }

    public function testActionsRejectAConditionalReturningAction(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $rule = $binder->bind('CREATE RULE r AS ON INSERT TO t DO INSTEAD INSERT INTO t VALUES (NEW.a) RETURNING a');
        $condition = $binder->bind('SELECT true');
        self::assertInstanceOf(CreateCommandRuleStatement::class, $rule);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $condition);
        $this->expectException(InvalidStructure::class);
        RuleInvariant::actions('r', RuleEvent::Insert, $rule->actions, true, $condition->outputs[0]->expression, false);
    }

    public function testActionsRejectAConditionalSelectRule(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT true');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        RuleInvariant::actions('_RETURN', RuleEvent::Select, [$query], true, $query->outputs[0]->expression, true);
    }

    public function testActionsRejectASelectRuleWithTwoQueries(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        RuleInvariant::actions('_RETURN', RuleEvent::Select, [$query, $query], true, null, true);
    }
}
