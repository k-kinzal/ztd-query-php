<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Decision;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\MergeStatement;
use SqlSemantics\Model\Write\Decision\ActionKind;
use SqlSemantics\Model\Write\Decision\MatchKind;
use SqlSemantics\Model\Write\Decision\MergeNothing;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MergeNothing::class)]
#[Medium]
final class MergeNothingTest extends TestCase
{
    public function testBindsADecisionWithoutEffect(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('MERGE INTO t USING s ON t.id=s.id WHEN MATCHED THEN DO NOTHING');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $action = $statement->merge->actions[0];
        self::assertInstanceOf(MergeNothing::class, $action);
        self::assertSame(ActionKind::Nothing, $action->action);
        self::assertSame(MatchKind::Matched, $action->match);
        self::assertNull($action->condition);
        $expected = 'MERGE INTO "public"."t" USING "public"."s" ON ("t"."id" = "s"."id") WHEN MATCHED THEN DO NOTHING';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith([MatchKind::Matched])]
    #[TestWith([MatchKind::MissingSource])]
    #[TestWith([MatchKind::MissingTarget])]
    public function testAcceptsEveryMatchCategory(MatchKind $match): void
    {
        $source = new Node('action', 0, []);
        $action = new MergeNothing($match, null, $source);
        self::assertSame($match, $action->match);
        self::assertSame(ActionKind::Nothing, $action->action);
        self::assertSame($source, $action->source);
    }
}
