<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation\Joining;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\Relation\Joining\UnconditionalOuterJoin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UnconditionalOuterJoin::class)]
#[Medium]
final class UnconditionalOuterJoinTest extends TestCase
{
    #[TestWith(['SELECT a.id FROM t a LEFT JOIN t b', JoinKind::Left, 'SELECT "a"."id" AS "id" FROM "main"."t" AS "a" LEFT JOIN "main"."t" AS "b"'])]
    #[TestWith(['SELECT a.id FROM t a RIGHT OUTER JOIN t b', JoinKind::Right, 'SELECT "a"."id" AS "id" FROM "main"."t" AS "a" RIGHT JOIN "main"."t" AS "b"'])]
    #[TestWith(['SELECT a.id FROM t a FULL JOIN t b', JoinKind::Full, 'SELECT "a"."id" AS "id" FROM "main"."t" AS "a" FULL JOIN "main"."t" AS "b"'])]
    public function testWithInputsPreservesTheOuterJoinKind(string $sql, JoinKind $kind, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $join = $query->from;
        self::assertInstanceOf(UnconditionalOuterJoin::class, $join);
        self::assertSame($kind, $join->kind);
        $changed = $join->withInputs($join->right, $join->left);
        self::assertNotSame($join, $changed);
        self::assertSame($kind, $changed->kind);
        self::assertSame($join->right, $changed->left);
        self::assertSame($join->id, $changed->id);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith([JoinKind::Inner])]
    #[TestWith([JoinKind::Cross])]
    public function testRejectsJoinKindsThatNeedNoNullExtension(JoinKind $kind): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT a.id FROM t a LEFT JOIN t b');
        self::assertInstanceOf(BoundSelect::class, $query);
        $join = $query->from;
        self::assertInstanceOf(UnconditionalOuterJoin::class, $join);
        $this->expectException(InvalidStructure::class);
        new UnconditionalOuterJoin($join->id, $kind, $join->left, $join->right, $join->source);
    }
}
