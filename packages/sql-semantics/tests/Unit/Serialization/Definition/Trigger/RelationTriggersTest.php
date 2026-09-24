<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\TransitionTable;
use SqlSemantics\Model\Definition\Trigger\TriggerEvent;
use SqlSemantics\Model\Definition\Trigger\TriggerEvents;
use SqlSemantics\Model\Definition\Trigger\TriggerInvocation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Trigger\RelationTriggers;

#[CoversClass(RelationTriggers::class)]
#[Medium]
final class RelationTriggersTest extends TestCase
{
    #[TestWith(['CREATE TRIGGER "x" AFTER INSERT OR DELETE ON "public"."t" FOR EACH STATEMENT EXECUTE FUNCTION "f"()'])]
    #[TestWith(['CREATE OR REPLACE TRIGGER "x" INSTEAD OF UPDATE ON "public"."t" FOR EACH ROW EXECUTE FUNCTION "f"(\'a\')'])]
    #[TestWith(['CREATE TRIGGER "x" BEFORE UPDATE OF "a" OR INSERT ON "public"."t" FOR EACH ROW WHEN (("new"."a" > 1)) EXECUTE FUNCTION "f"()'])]
    #[TestWith(['CREATE TRIGGER "x" AFTER DELETE ON "public"."t" REFERENCING OLD TABLE AS "o" FOR EACH ROW EXECUTE FUNCTION "f"()'])]
    #[TestWith(['CREATE CONSTRAINT TRIGGER "x" AFTER INSERT ON "public"."t" FROM "public"."t" DEFERRABLE FOR EACH ROW EXECUTE FUNCTION "f"()'])]
    #[TestWith(['CREATE CONSTRAINT TRIGGER "x" AFTER INSERT ON "public"."t" FOR EACH ROW WHEN (("new"."a" > 1)) EXECUTE FUNCTION "f"()'])]
    public function testWriteIsAFixedPoint(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        self::assertSame($sql, $binder->bind($sql)->toString());
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        self::assertNull(RelationTriggers::write($binder->bind('DROP TRIGGER x ON t')));
    }

    public function testEventsJoinsEventsWithOr(): void
    {
        self::assertSame('TRUNCATE OR UPDATE OF "a", "b"', RelationTriggers::events(new TriggerEvents([TriggerEvent::Truncate, TriggerEvent::Update], ['a', 'b']))->toString());
    }

    public function testTransitionsIsEmptyWithoutTransitionTables(): void
    {
        self::assertSame([], RelationTriggers::transitions([]));
        self::assertCount(2, RelationTriggers::transitions([new TransitionTable(RowVersion::New, 'n')]));
    }

    public function testConditionIsEmptyWithoutACondition(): void
    {
        self::assertSame([], RelationTriggers::condition(null));
    }

    public function testInvocationQuotesEveryArgument(): void
    {
        self::assertSame('EXECUTE FUNCTION "f"(\'1\', \'a\'\'b\')', RelationTriggers::invocation(new TriggerInvocation(new QualifiedName(['f']), ['1', "a'b"]))->toString());
    }
}
