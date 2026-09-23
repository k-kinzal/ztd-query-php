<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Query\Locking\AllRowLock::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class AllRowLockTest extends TestCase
{
    public function testPreservesALegacyMysqlSharedLock(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('SELECT id FROM t LOCK IN SHARE MODE');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\AllRowLock::class, $statement->locks[0]);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Share, $statement->locks[0]->strength);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testDoesNotLeakFromANestedQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('SELECT q.id FROM (SELECT id FROM t FOR UPDATE) q');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame([], $statement->locks);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DerivedRelation::class, $statement->from);
        self::assertInstanceOf(BoundSelect::class, $statement->from->query);
        self::assertCount(1, $statement->from->query->locks);
    }
}
