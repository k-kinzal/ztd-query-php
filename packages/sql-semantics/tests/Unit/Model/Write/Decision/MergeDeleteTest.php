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
use SqlSemantics\Model\Write\Decision\ActionKind;
use SqlSemantics\Model\Write\Decision\MatchKind;
use SqlSemantics\Model\Write\Decision\MergeDelete;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MergeDelete::class)]
#[Medium]
final class MergeDeleteTest extends TestCase
{
    public function testBindsAMatchedDeletionWithItsCondition(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED AND s.id>0 THEN DELETE');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeDelete::class, $action);
        self::assertSame(ActionKind::Delete, $action->action);
        self::assertSame(MatchKind::Matched, $action->match);
        self::assertSame('>', $action->condition?->spelling());
        $expected = 'MERGE INTO "public"."t" USING "public"."s" ON ("t"."id" = "s"."id") WHEN MATCHED AND ("s"."id" > 0) THEN DELETE';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testAcceptsAMissingSourceMatchWithoutACondition(): void
    {
        $source = new Node('action', 0, []);
        $action = new MergeDelete(MatchKind::MissingSource, null, $source);
        self::assertSame(MatchKind::MissingSource, $action->match);
        self::assertNull($action->condition);
        self::assertSame(ActionKind::Delete, $action->action);
        self::assertSame($source, $action->source);
    }

    public function testRejectsAMissingTarget(): void
    {
        $this->expectException(InvalidStructure::class);
        new MergeDelete(MatchKind::MissingTarget, null, new Node('action', 0, []));
    }
}
