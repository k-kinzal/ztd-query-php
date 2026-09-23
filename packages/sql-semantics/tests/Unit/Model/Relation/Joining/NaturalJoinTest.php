<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation\Joining;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\Joining\NaturalJoin;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NaturalJoin::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class NaturalJoinTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testWithInputsPreservesTheMatchingOperation(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $query = $binder->bind('SELECT * FROM t AS a NATURAL LEFT JOIN t AS b');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $join = $query->from;
        self::assertInstanceOf(NaturalJoin::class, $join);
        self::assertSame(['id', 'n'], array_column($join->columns, 'name'));
        $changed = $join->withInputs($join->left, $join->right);
        self::assertNotSame($join, $changed);
        self::assertSame($join->left, $changed->left);
        $rebound = $binder->bind($query->withFrom($changed)->toString());
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $rebound);
        self::assertInstanceOf(NaturalJoin::class, $rebound->from);
        self::assertSame(array_column($query->outputs, 'name'), array_column($rebound->outputs, 'name'));
    }
}
