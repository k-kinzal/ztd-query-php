<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Locking\LockWait;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LockWait::class)]
#[Medium]
final class LockWaitTest extends TestCase
{
    public function testRepresentsEveryContentionPolicy(): void
    {
        self::assertSame(['', 'NOWAIT', 'SKIP LOCKED'], array_column(LockWait::cases(), 'value'));
    }

    #[TestWith(['SELECT id FROM t FOR UPDATE NOWAIT', LockWait::NoWait, 'SELECT "id" AS "id" FROM "public"."t" FOR UPDATE NOWAIT'])]
    #[TestWith(['SELECT id FROM t FOR UPDATE SKIP LOCKED', LockWait::SkipLocked, 'SELECT "id" AS "id" FROM "public"."t" FOR UPDATE SKIP LOCKED'])]
    #[TestWith(['SELECT id FROM t FOR UPDATE', LockWait::Wait, 'SELECT "id" AS "id" FROM "public"."t" FOR UPDATE'])]
    public function testBindsThePolicyAndWritesItBack(string $sql, LockWait $wait, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($wait, $statement->locks[0]->wait);
        self::assertSame($expected, $statement->toString());
    }
}
