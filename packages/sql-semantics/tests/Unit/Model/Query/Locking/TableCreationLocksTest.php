<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Locking\AllRowLock;
use SqlSemantics\Model\Query\Locking\LockStrength;
use SqlSemantics\Model\Query\Locking\TableCreationLocks;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableCreationLocks::class)]
#[Medium]
final class TableCreationLocksTest extends TestCase
{
    #[TestWith(['SELECT a FROM t FOR UPDATE', true])]
    #[TestWith(['SELECT t.a FROM t, u FOR UPDATE OF u NOWAIT', true])]
    #[TestWith(['SELECT a FROM t WHERE a IN (SELECT a FROM u FOR UPDATE)', true])]
    #[TestWith(['SELECT a FROM (SELECT a FROM u FOR UPDATE SKIP LOCKED) d', true])]
    #[TestWith(['WITH c AS (SELECT a FROM u FOR UPDATE) SELECT a FROM c', true])]
    #[TestWith(['SELECT a FROM t UNION SELECT a FROM u FOR UPDATE', true])]
    #[TestWith(['TABLE t FOR UPDATE', true])]
    #[TestWith(['SELECT a FROM t FOR SHARE', false])]
    #[TestWith(['SELECT a FROM t LOCK IN SHARE MODE', false])]
    #[TestWith(['SELECT 1 AS a FOR UPDATE', false])]
    #[TestWith(['SELECT a FROM (SELECT a FROM u) d FOR UPDATE', false])]
    #[TestWith(['WITH c AS (SELECT a FROM u) SELECT a FROM c FOR UPDATE', false])]
    #[TestWith(['VALUES ROW(1) FOR UPDATE', false])]
    public function testExclusiveFindsAnExclusiveLockOnAStoredTableInAnyQueryBlock(string $sql, bool $expected): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind($sql);
        self::assertInstanceOf(BoundQuery::class, $query);
        self::assertSame($expected, TableCreationLocks::exclusive($query));
    }

    public function testLocksStoredReadsTheCoveredRelationsOfOneQueryBlock(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t FOR SHARE OF t');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertFalse(TableCreationLocks::locksStored($query->locks, $query->relations));
        self::assertTrue(TableCreationLocks::locksStored([new AllRowLock(LockStrength::Update)], $query->relations));
        self::assertFalse(TableCreationLocks::locksStored([new AllRowLock(LockStrength::Update)], []));
    }

    public function testOperandSkipsProvenanceAndSchemaDeclarations(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertTrue(TableCreationLocks::operand($query));
        self::assertTrue(TableCreationLocks::operand($query->relations[0]));
        self::assertFalse(TableCreationLocks::operand($query->origin));
        self::assertFalse(TableCreationLocks::operand($query->relations[0]->declaration));
        self::assertFalse(TableCreationLocks::operand($query->source));
    }
}
