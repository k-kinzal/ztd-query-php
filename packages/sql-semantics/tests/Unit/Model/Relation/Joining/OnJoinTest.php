<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation\Joining;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\Joining\OnJoin;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OnJoin::class)]
final class OnJoinTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testWithInputsPreservesTheMatchingOperation(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $query = $binder->bind('SELECT * FROM t AS a LEFT JOIN t AS b ON a.id=b.id');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $join = $query->from;
        self::assertInstanceOf(OnJoin::class, $join);
        self::assertSame('=', $join->condition->spelling());
        $changed = $join->withInputs($join->left, $join->right);
        self::assertNotSame($join, $changed);
        self::assertSame($join->left, $changed->left);
        $rebound = $binder->bind($query->withFrom($changed)->toString());
        self::assertInstanceOf(OnJoin::class, $rebound->from);
        self::assertSame(array_column($query->outputs, 'name'), array_column($rebound->outputs, 'name'));
    }
}
