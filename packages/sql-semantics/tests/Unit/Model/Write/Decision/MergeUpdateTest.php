<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Decision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\MergeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\Model\Write\Decision\ActionKind;
use SqlSemantics\Model\Write\Decision\MatchKind;
use SqlSemantics\Model\Write\Decision\MergeUpdate;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MergeUpdate::class)]
#[Medium]
final class MergeUpdateTest extends TestCase
{
    public function testBindsAMatchedUpdateWithItsAssignments(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)', 'CREATE TABLE s(id INTEGER, n INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED AND s.n>0 THEN UPDATE SET n=s.n, id=DEFAULT');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeUpdate::class, $action);
        self::assertSame(ActionKind::Update, $action->action);
        self::assertSame(MatchKind::Matched, $action->match);
        self::assertSame('>', $action->condition?->spelling());
        self::assertCount(2, $action->assignments);
        self::assertInstanceOf(ScalarAssignment::class, $action->assignments[0]);
        self::assertSame('n', $action->assignments[0]->target->column()->columnBinding()?->column->name);
        $expected = 'MERGE INTO "public"."t" USING "public"."s" ON ("t"."id" = "s"."id") WHEN MATCHED AND ("s"."n" > 0) THEN UPDATE SET "n" = "s"."n", "id" = DEFAULT';
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testAcceptsAMissingSourceMatch(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $statement = (new Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN NOT MATCHED BY SOURCE THEN UPDATE SET id=0');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeUpdate::class, $action);
        self::assertSame(MatchKind::MissingSource, $action->match);
        self::assertNull($action->condition);
    }

    public function testRejectsEmptyAssignments(): void
    {
        $this->expectException(InvalidStructure::class);
        new MergeUpdate(MatchKind::Matched, null, new Node('action', 0, []), []);
    }

    public function testRejectsAMissingTarget(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $statement = (new Binder($schema))->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED THEN UPDATE SET id=s.id');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeUpdate::class, $action);
        $this->expectException(InvalidStructure::class);
        new MergeUpdate(MatchKind::MissingTarget, null, $action->source, $action->assignments);
    }
}
