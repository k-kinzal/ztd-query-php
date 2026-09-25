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
        self::assertSame('SELECT "t"."a" AS "a" FROM "public"."t" CROSS JOIN "public"."u" FOR UPDATE OF "t" NOWAIT FOR SHARE SKIP LOCKED FOR NO KEY UPDATE OF "t", "u"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindTranslatesMySqlShareModeSyntax(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t LOCK IN SHARE MODE');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\AllRowLock::class, $statement->locks[0]);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Share, $statement->locks[0]->strength);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockWait::Wait, $statement->locks[0]->wait);
        self::assertSame('SELECT `a` AS `a` FROM `t` LOCK IN SHARE MODE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
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
        self::assertSame('SELECT `a` AS `a` FROM `t` WHERE (`a` > 0) ' . $lock, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['SELECT a FROM t FOR UPDATE OF t FOR SHARE OF t'])]
    #[TestWith(['SELECT a FROM t FOR UPDATE OF t, t'])]
    #[TestWith(['SELECT a FROM t AS z FOR UPDATE OF z FOR SHARE OF z'])]
    #[TestWith(['SELECT a FROM t FOR UPDATE FOR SHARE'])]
    #[TestWith(['SELECT a FROM t FOR UPDATE FOR SHARE OF t'])]
    #[TestWith(['SELECT t.a FROM t, u FOR UPDATE OF t LOCK IN SHARE MODE'])]
    #[TestWith(['TABLE t FOR UPDATE FOR SHARE'])]
    #[TestWith(['(SELECT a FROM t FOR SHARE) FOR UPDATE'])]
    #[TestWith(['SELECT a FROM t UNION SELECT a FROM u FOR UPDATE OF u FOR SHARE OF u'])]
    #[TestWith(['CREATE VIEW v AS SELECT a FROM t FOR UPDATE OF t FOR SHARE OF t'])]
    public function testBindRejectsATableLockedByTwoMySqlClauses(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::RepeatedLock->message());
        $binder->bind($sql);
    }

    #[TestWith(['SELECT t.a FROM t, u FOR UPDATE OF t FOR SHARE OF u', 'SELECT `t`.`a` AS `a` FROM `t` CROSS JOIN `u` FOR UPDATE OF `t` FOR SHARE OF `u`'])]
    #[TestWith(['SELECT x.a FROM t x, t y FOR UPDATE OF x FOR SHARE OF y', 'SELECT `x`.`a` AS `a` FROM `t` AS `x` CROSS JOIN `t` AS `y` FOR UPDATE OF `x` FOR SHARE OF `y`'])]
    #[TestWith(['SELECT 1 FOR UPDATE FOR SHARE', 'SELECT 1 FOR UPDATE LOCK IN SHARE MODE'])]
    #[TestWith(['SELECT a FROM (SELECT 1 AS a) d FOR UPDATE FOR SHARE', 'SELECT `a` AS `a` FROM(SELECT 1 AS `a`) AS `d` FOR UPDATE LOCK IN SHARE MODE'])]
    public function testBindKeepsMySqlClausesThatLockDifferentTables(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)'));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
    }

    #[TestWith(['mysql-8.0.44', 'SELECT * FROM (SELECT a FROM t) AS d FOR UPDATE OF d'])]
    #[TestWith(['mysql-8.4.7', 'SELECT * FROM t, (SELECT a FROM u) AS d FOR SHARE OF d'])]
    #[TestWith(['mysql-8.4.7', 'SELECT * FROM t, (SELECT a FROM u) AS d FOR UPDATE OF t, d'])]
    #[TestWith(['mysql-8.4.7', 'SELECT * FROM JSON_TABLE(JSON_ARRAY(1), "$[*]" COLUMNS (x INT PATH "$")) AS j FOR UPDATE OF j'])]
    #[TestWith(['mysql-8.4.7', 'SELECT * FROM t JOIN LATERAL (SELECT t.a) AS d ON TRUE FOR UPDATE OF d'])]
    #[TestWith(['mysql-8.4.7', 'SELECT * FROM (VALUES ROW(1)) AS v FOR UPDATE OF v'])]
    #[TestWith(['mysql-9.1.0', 'SELECT a FROM t WHERE a IN (SELECT a FROM (SELECT a FROM u) AS d FOR SHARE OF d)'])]
    public function testBindRejectsADerivedTableNamedAsAMySqlLockTarget(string $version, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::DerivedLockTarget->message());
        $binder->bind($sql);
    }

    #[TestWith(['WITH c AS (SELECT a FROM t) SELECT * FROM c FOR UPDATE OF c', 'WITH `c` AS (SELECT `a` AS `a` FROM `t`) SELECT `c`.`a` AS `a` FROM `c` FOR UPDATE OF `c`'])]
    #[TestWith(['SELECT * FROM t, (SELECT a FROM u) AS d FOR UPDATE OF t', 'SELECT `t`.`a` AS `a`, `d`.`a` AS `a` FROM `t` CROSS JOIN(SELECT `a` AS `a` FROM `u`) AS `d` FOR UPDATE OF `t`'])]
    #[TestWith(['SELECT * FROM (SELECT a FROM t) AS d FOR UPDATE', 'SELECT `d`.`a` AS `a` FROM(SELECT `a` AS `a` FROM `t`) AS `d` FOR UPDATE'])]
    public function testBindKeepsMySqlLockTargetsThatAreTablesOrCommonTableExpressions(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)'));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testBindKeepsRepeatedPostgreSqlTargetsWhichTheServerMerges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t FOR UPDATE OF t FOR SHARE OF t, t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertCount(2, $statement->locks);
        self::assertSame('SELECT "a" AS "a" FROM "public"."t" FOR UPDATE OF "t" FOR SHARE OF "t", "t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['mysql-5.6.51', 'SELECT a FROM (SELECT a FROM t FOR UPDATE) d', 'SELECT `a` AS `a` FROM(SELECT `a` AS `a` FROM `t` FOR UPDATE) AS `d`'])]
    #[TestWith(['mysql-5.7.44', 'SELECT a FROM (SELECT a FROM t LOCK IN SHARE MODE) d FOR UPDATE', 'SELECT `a` AS `a` FROM(SELECT `a` AS `a` FROM `t` LOCK IN SHARE MODE) AS `d` FOR UPDATE'])]
    #[TestWith(['mysql-8.0.44', 'SELECT a FROM (SELECT a FROM t FOR UPDATE) d', 'SELECT `a` AS `a` FROM(SELECT `a` AS `a` FROM `t` FOR UPDATE) AS `d`'])]
    #[TestWith(['mysql-8.4.7', 'SELECT a FROM ((SELECT a FROM t FOR SHARE OF t NOWAIT)) d', 'SELECT `a` AS `a` FROM(SELECT `a` AS `a` FROM `t` FOR SHARE OF `t` NOWAIT) AS `d`'])]
    #[TestWith(['mysql-9.1.0', 'SELECT u.a FROM u, LATERAL (SELECT a FROM t FOR UPDATE SKIP LOCKED) d', 'SELECT `u`.`a` AS `a` FROM `u` CROSS JOIN LATERAL(SELECT `a` AS `a` FROM `t` FOR UPDATE SKIP LOCKED) AS `d`'])]
    public function testBindKeepsTheLockingClauseOfADerivedTable(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)'));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
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
        self::assertSame('(SELECT `a` AS `a` FROM `t` FOR UPDATE) UNION SELECT `a` AS `a` FROM `t`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['mysql-8.0.44', 'SELECT a FROM t UNION SELECT a FROM u FOR UPDATE', 'SELECT `a` AS `a` FROM `t` UNION (SELECT `a` AS `a` FROM `u` FOR UPDATE)'])]
    #[TestWith(['mysql-8.4.7', 'SELECT a FROM t UNION SELECT a FROM u LOCK IN SHARE MODE', 'SELECT `a` AS `a` FROM `t` UNION (SELECT `a` AS `a` FROM `u` LOCK IN SHARE MODE)'])]
    #[TestWith(['mysql-9.1.0', 'SELECT a FROM t UNION SELECT a FROM u FOR SHARE OF u NOWAIT', 'SELECT `a` AS `a` FROM `t` UNION (SELECT `a` AS `a` FROM `u` FOR SHARE OF `u` NOWAIT)'])]
    #[TestWith(['mysql-8.0.44', 'TABLE t UNION TABLE u FOR UPDATE', 'TABLE `t` UNION (TABLE `u` FOR UPDATE)'])]
    #[TestWith(['mysql-8.4.7', 'SELECT a FROM t EXCEPT SELECT a FROM u ORDER BY a LIMIT 5 FOR UPDATE SKIP LOCKED', 'SELECT `a` AS `a` FROM `t` EXCEPT (SELECT `a` AS `a` FROM `u` FOR UPDATE SKIP LOCKED) ORDER BY `a` ASC LIMIT 5'])]
    #[TestWith(['mysql-9.1.0', 'SELECT a FROM t UNION VALUES ROW(1) FOR UPDATE', 'SELECT `a` AS `a` FROM `t` UNION (VALUES ROW(1) FOR UPDATE)'])]
    #[TestWith(['mysql-8.0.44', '(SELECT a FROM t UNION SELECT a FROM u) FOR UPDATE', 'SELECT `a` AS `a` FROM `t` UNION (SELECT `a` AS `a` FROM `u` FOR UPDATE)'])]
    #[TestWith(['mysql-8.4.7', 'SELECT a FROM u UNION (SELECT a FROM u UNION SELECT a FROM t) FOR UPDATE OF t', 'SELECT `a` AS `a` FROM `u` UNION (SELECT `a` AS `a` FROM `u` UNION (SELECT `a` AS `a` FROM `t` FOR UPDATE OF `t`))'])]
    #[TestWith(['mysql-9.1.0', 'SELECT a FROM t INTERSECT SELECT a FROM u UNION SELECT a FROM u FOR SHARE', 'SELECT `a` AS `a` FROM `t` INTERSECT SELECT `a` AS `a` FROM `u` UNION (SELECT `a` AS `a` FROM `u` LOCK IN SHARE MODE)'])]
    #[TestWith(['mysql-8.4.7', 'SELECT * FROM (SELECT a FROM t UNION SELECT a FROM u FOR UPDATE) x', 'SELECT `x`.`a` AS `a` FROM(SELECT `a` AS `a` FROM `t` UNION (SELECT `a` AS `a` FROM `u` FOR UPDATE)) AS `x`'])]
    #[TestWith(['mysql-5.7.44', 'SELECT a FROM t UNION SELECT a FROM u FOR UPDATE', 'SELECT `a` AS `a` FROM `t` UNION (SELECT `a` AS `a` FROM `u` FOR UPDATE)'])]
    #[TestWith(['mysql-5.6.51', 'SELECT a FROM t UNION SELECT a FROM u LOCK IN SHARE MODE', 'SELECT `a` AS `a` FROM `t` UNION (SELECT `a` AS `a` FROM `u` LOCK IN SHARE MODE)'])]
    public function testTrailingLockOfASetOperationLocksTheLastQueryBlock(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testTrailingLockNamesOnlyTablesOfTheLastQueryBlock(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)'));
        $statement = $binder->bind('SELECT a FROM t UNION SELECT a FROM u FOR SHARE OF t NOWAIT', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CompoundStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->left);
        self::assertSame([], $statement->left->locks);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->right);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\NamedRowLock::class, $statement->right->locks[0]);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Share, $statement->right->locks[0]->strength);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockWait::NoWait, $statement->right->locks[0]->wait);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\UnresolvedLockRelation::class, $statement->right->locks[0]->relations[0]);
        self::assertSame('unknown-lock-relation', $statement->diagnostics[0]->reason);
    }

    #[TestWith(['SELECT a FROM t UNION SELECT a FROM u FOR UPDATE', 'FOR UPDATE'])]
    #[TestWith(['(SELECT a FROM t UNION SELECT a FROM u FOR SHARE) FOR UPDATE', 'FOR SHARE|FOR UPDATE'])]
    #[TestWith(['SELECT a FROM t UNION (SELECT a FROM u FOR UPDATE)', ''])]
    #[TestWith(['SELECT a FROM t UNION SELECT a FROM u', ''])]
    public function testTrailingFindsTheLockListsWrittenAfterTheSetOperation(string $sql, string $locks): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql);
        $found = LockingBinder::trailing($tree, \SqlSemantics\Binding\Query\QueryNodes::body($tree));
        self::assertSame($locks, implode('|', array_map(\SqlSemantics\Ast\Tree::text(...), $found)));
    }

    public function testValuesKeepsTheLocksOfAMySqlValuesBlock(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('VALUES ROW(1), ROW(2) FOR SHARE SKIP LOCKED');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\ValuesStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Share, $statement->locks[0]->strength);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockWait::SkipLocked, $statement->locks[0]->wait);
        self::assertSame('VALUES ROW(1), ROW(2) FOR SHARE SKIP LOCKED', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['VALUES (1) FOR UPDATE'])]
    #[TestWith(['(VALUES (1)) FOR KEY SHARE'])]
    public function testValuesRejectsAPostgreSqlLock(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ValuesLock->message());
        $binder->bind($sql);
    }
}
