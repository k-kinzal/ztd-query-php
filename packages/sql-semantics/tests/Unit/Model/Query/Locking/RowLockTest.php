<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Locking\AllRowLock;
use SqlSemantics\Model\Query\Locking\LockStrength;
use SqlSemantics\Model\Query\Locking\LockWait;
use SqlSemantics\Model\Query\Locking\NamedRowLock;
use SqlSemantics\Model\Query\Locking\RowLock;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowLock::class)]
#[Medium]
final class RowLockTest extends TestCase
{
    public function testWaitsByDefault(): void
    {
        $lock = new AllRowLock(LockStrength::Share);
        self::assertSame(LockStrength::Share, $lock->strength);
        self::assertSame(LockWait::Wait, $lock->wait);
    }

    public function testRetainsTheRequestedStrengthAndContentionPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t FOR SHARE NOWAIT');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertCount(1, $statement->locks);
        self::assertSame(LockStrength::Share, $statement->locks[0]->strength);
        self::assertSame(LockWait::NoWait, $statement->locks[0]->wait);
    }

    public function testDistinguishesGlobalFromTargetedLocks(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('SELECT a.id FROM t a FOR UPDATE FOR SHARE OF a');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(AllRowLock::class, $statement->locks[0]);
        self::assertInstanceOf(NamedRowLock::class, $statement->locks[1]);
        self::assertSame('SELECT "a"."id" AS "id" FROM "public"."t" AS "a" FOR UPDATE FOR SHARE OF "a"', $statement->toString());
    }
}
