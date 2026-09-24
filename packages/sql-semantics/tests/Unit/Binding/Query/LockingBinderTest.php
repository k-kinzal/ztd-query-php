<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\LockingBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LockingBinder::class)]
#[Medium]
final class LockingBinderTest extends TestCase
{
    public function testBindKeepsEachLockingClauseWithItsStrengthWaitAndTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind('SELECT t.a FROM t, u FOR UPDATE OF t NOWAIT FOR SHARE SKIP LOCKED FOR NO KEY UPDATE OF t, u');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertCount(3, $statement->locks);
        [$named, $all, $both] = $statement->locks;
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\NamedRowLock::class, $named);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Update, $named->strength);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockWait::NoWait, $named->wait);
        self::assertSame([$statement->relations[0]], $named->relations);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\AllRowLock::class, $all);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Share, $all->strength);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockWait::SkipLocked, $all->wait);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\NamedRowLock::class, $both);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::NoKeyUpdate, $both->strength);
        self::assertSame($statement->relations, $both->relations);
        self::assertSame('SELECT "t"."a" AS "a" FROM "public"."t" CROSS JOIN "public"."u" FOR UPDATE OF "t" NOWAIT FOR SHARE SKIP LOCKED FOR NO KEY UPDATE OF "t", "u"', $statement->toString());
    }

    public function testBindTranslatesMySqlShareModeSyntax(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t LOCK IN SHARE MODE');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\AllRowLock::class, $statement->locks[0]);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Share, $statement->locks[0]->strength);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockWait::Wait, $statement->locks[0]->wait);
        self::assertSame('SELECT `a` AS `a` FROM `t` LOCK IN SHARE MODE', $statement->toString());
    }

    #[TestWith(['mysql-5.6.51', 'FOR UPDATE'])]
    #[TestWith(['mysql-5.7.44', 'FOR UPDATE'])]
    #[TestWith(['mysql-5.7.44', 'LOCK IN SHARE MODE'])]
    #[TestWith(['mysql-8.4.7', 'FOR UPDATE'])]
    public function testBindKeepsTheLegacyLockClauseOfEachRelease(string $version, string $lock): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('SELECT a FROM t WHERE a > 0 ' . $lock);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertCount(1, $statement->locks);
        self::assertSame('SELECT `a` AS `a` FROM `t` WHERE (`a` > 0) ' . $lock, $statement->toString());
    }

    public function testTargetReportsAnUnknownLockRelationWithoutFailing(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t FOR KEY SHARE OF missing', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\NamedRowLock::class, $statement->locks[0]);
        $target = $statement->locks[0]->relations[0];
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\UnresolvedLockRelation::class, $target);
        self::assertSame(['missing'], $target->name->parts);
        self::assertSame('unknown-lock-relation', $statement->diagnostics[0]->reason);
        self::assertSame('Cannot resolve lock target: missing', $statement->diagnostics[0]->message);
    }

    public function testTargetReportsAnAmbiguousAlias(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE SCHEMA a', 'CREATE TABLE a.t(a INT)', 'CREATE TABLE t(a INT)')))->bind('SELECT 1 FROM a.t, public.t FOR UPDATE OF t', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\NamedRowLock::class, $statement->locks[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\UnresolvedLockRelation::class, $statement->locks[0]->relations[0]);
        self::assertContains('ambiguous-lock-relation', array_column($statement->diagnostics, 'reason'));
    }

    #[TestWith(['(SELECT 1 FOR UPDATE) UNION SELECT 2', 'FOR UPDATE'])]
    #[TestWith(['SELECT 1 INTERSECT ((SELECT 2 FOR SHARE NOWAIT))', 'FOR SHARE NOWAIT'])]
    #[TestWith(['SELECT 1 EXCEPT SELECT 2 FOR KEY SHARE', 'FOR KEY SHARE'])]
    #[TestWith(['(SELECT 1 UNION SELECT 2) FOR NO KEY UPDATE', 'FOR NO KEY UPDATE'])]
    #[TestWith(['(SELECT 1 UNION (SELECT 2 FOR UPDATE)) UNION SELECT 3', 'FOR UPDATE'])]
    public function testSetOperationLockFindsTheLockOfTheSetOperationOrAnOperand(string $sql, string $lock): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse($sql);
        $body = \SqlSemantics\Binding\Query\QueryNodes::body($tree);
        $found = LockingBinder::setOperationLock($tree, $body);
        self::assertNotNull($found);
        self::assertSame($lock, \SqlSemantics\Ast\Tree::text($found));
    }

    #[TestWith(['SELECT * FROM (SELECT 1 FOR UPDATE) s UNION SELECT 2'])]
    #[TestWith(['WITH c AS (SELECT 1 FOR UPDATE) SELECT 1 UNION SELECT 2'])]
    #[TestWith(['SELECT 1 UNION SELECT (SELECT 2 FOR UPDATE)'])]
    public function testSetOperationLockLeavesTheLocksOfNestedSubqueries(string $sql): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse($sql);
        self::assertNull(LockingBinder::setOperationLock($tree, \SqlSemantics\Binding\Query\QueryNodes::body($tree)));
    }

    public function testOperandLockLooksThroughParentheses(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('((SELECT 1 FOR SHARE))');
        $found = LockingBinder::operandLock(\SqlSemantics\Ast\Tree::outer($tree, ['select_with_parens'])[0]);
        self::assertNotNull($found);
        self::assertSame('FOR SHARE', \SqlSemantics\Ast\Tree::text($found));
        self::assertNull(LockingBinder::operandLock((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1')));
    }

    public function testAttachedLockReadsOnlyDirectChildren(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1 FOR UPDATE');
        $query = \SqlSemantics\Ast\Tree::outer($tree, ['select_no_parens'])[0];
        $found = LockingBinder::attachedLock($query);
        self::assertNotNull($found);
        self::assertSame('FOR UPDATE', \SqlSemantics\Ast\Tree::text($found));
        self::assertNull(LockingBinder::attachedLock($tree));
    }

    public function testContainsFindsADescendantOnly(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1 FOR UPDATE');
        $lock = \SqlSemantics\Ast\Tree::outer($tree, ['for_locking_clause'])[0];
        self::assertTrue(LockingBinder::contains($tree, $lock));
        self::assertFalse(LockingBinder::contains($lock, $tree));
    }

    #[TestWith(['(SELECT a FROM t FOR UPDATE) UNION SELECT a FROM t'])]
    #[TestWith(['SELECT a FROM t UNION ALL (SELECT a FROM t FOR SHARE)'])]
    #[TestWith(['(SELECT a FROM t FOR UPDATE) INTERSECT SELECT a FROM t'])]
    #[TestWith(['(SELECT a FROM t FOR NO KEY UPDATE) EXCEPT SELECT a FROM t'])]
    #[TestWith(['SELECT a FROM t UNION SELECT a FROM t FOR UPDATE'])]
    public function testSetOperationLockIsRejectedByPostgreSql(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        try {
            $binder->bind($sql);
            self::fail('PostgreSQL rejects a locking clause with a set operation.');
        } catch (\SqlSemantics\InvalidSql $error) {
            self::assertSame(\SqlSemantics\Model\Validation\InputViolation::SetOperationLock, $error->violation);
        }
    }

    public function testSetOperationLockOfAMySqlOperandIsKept(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('(SELECT a FROM t FOR UPDATE) UNION SELECT a FROM t');
        self::assertSame('(SELECT `a` AS `a` FROM `t` FOR UPDATE) UNION SELECT `a` AS `a` FROM `t`', $statement->toString());
    }
}
