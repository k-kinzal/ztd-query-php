<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Query\Locking\NamedRowLock::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class NamedRowLockTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    public function testBindsTargetOccurrencesAndContentionPolicy(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('SELECT a.id FROM t a FOR UPDATE OF a SKIP LOCKED');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $lock = $statement->locks[0];
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\NamedRowLock::class, $lock);
        self::assertSame([$statement->relations[0]], $lock->relations);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockWait::SkipLocked, $lock->wait);
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\NamedRowLock::class, $rebound->locks[0]);
        self::assertSame([$rebound->relations[0]], $rebound->locks[0]->relations);
    }

    public function testRequiresExplicitTargets(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new \SqlSemantics\Model\Query\Locking\NamedRowLock(\SqlSemantics\Model\Query\Locking\LockStrength::Update, []);
    }
}
