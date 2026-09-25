<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Locking\AllRowLock;
use SqlSemantics\Model\Query\Locking\LockStrength;
use SqlSemantics\Model\Query\Locking\LockWait;
use SqlSemantics\Model\Query\Locking\NamedRowLock;
use SqlSemantics\Model\Query\Locking\UnresolvedLockRelation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\RowLocks;

#[CoversClass(RowLocks::class)]
#[Medium]
final class RowLocksTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'SELECT id FROM t FOR UPDATE NOWAIT', 'FOR UPDATE NOWAIT'])]
    #[TestWith([Dialect::MySql, 'SELECT id FROM t FOR SHARE SKIP LOCKED', 'FOR SHARE SKIP LOCKED'])]
    #[TestWith([Dialect::MySql, 'SELECT id FROM t FOR SHARE', 'LOCK IN SHARE MODE'])]
    #[TestWith([Dialect::MySql, 'SELECT id FROM t LOCK IN SHARE MODE', 'LOCK IN SHARE MODE'])]
    #[TestWith([Dialect::MySql, 'SELECT id FROM t FOR SHARE OF t', 'FOR SHARE OF `t`'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT id FROM t FOR SHARE', 'FOR SHARE'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT id FROM t FOR UPDATE OF t NOWAIT', 'FOR UPDATE OF "t" NOWAIT'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT t.id FROM t, s FOR KEY SHARE OF s SKIP LOCKED', 'FOR KEY SHARE OF "s" SKIP LOCKED'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT id FROM t FOR NO KEY UPDATE SKIP LOCKED FOR KEY SHARE', 'FOR NO KEY UPDATE SKIP LOCKED FOR KEY SHARE'])]
    public function testWriteSerializesEachLockClauseInOrder(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT)', 'CREATE TABLE s(id INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, RowLocks::write($statement->locks, $dialect)->toString());
        self::assertStringEndsWith(' ' . $expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWriteIsEmptyWithoutLocks(): void
    {
        self::assertSame('', RowLocks::write([], Dialect::PostgreSql)->toString());
    }

    public function testClauseUsesTheLegacyMySqlSpellingOnlyForAWaitingSharedLockOnAllRows(): void
    {
        self::assertSame('LOCK IN SHARE MODE', RowLocks::clause(new AllRowLock(LockStrength::Share), Dialect::MySql)->toString());
        self::assertSame('FOR SHARE', RowLocks::clause(new AllRowLock(LockStrength::Share), Dialect::PostgreSql)->toString());
        self::assertSame('FOR SHARE NOWAIT', RowLocks::clause(new AllRowLock(LockStrength::Share, LockWait::NoWait), Dialect::MySql)->toString());
        self::assertSame('FOR UPDATE SKIP LOCKED', RowLocks::clause(new AllRowLock(LockStrength::Update, LockWait::SkipLocked), Dialect::MySql)->toString());
    }

    public function testClauseNamesAliasesAndUnresolvedRelations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('SELECT id FROM t AS a FOR SHARE OF a, missing', strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $lock = $statement->locks[0];
        self::assertInstanceOf(NamedRowLock::class, $lock);
        self::assertInstanceOf(UnresolvedLockRelation::class, $lock->relations[1]);
        self::assertSame('FOR SHARE OF "a", "missing"', RowLocks::clause($lock, Dialect::PostgreSql)->toString());
        self::assertSame('unknown-lock-relation', $statement->diagnostics[0]->reason);
    }
}
