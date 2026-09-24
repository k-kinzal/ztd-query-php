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
}
