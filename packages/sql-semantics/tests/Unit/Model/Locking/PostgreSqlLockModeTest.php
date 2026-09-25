<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Locking\PostgreSqlLockMode;
use SqlSemantics\Model\Statement\Locking\LockRelationsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlLockMode::class)]
#[Medium]
final class PostgreSqlLockModeTest extends TestCase
{
    #[TestWith(['ACCESS SHARE', PostgreSqlLockMode::AccessShare])]
    #[TestWith(['ROW SHARE', PostgreSqlLockMode::RowShare])]
    #[TestWith(['ROW EXCLUSIVE', PostgreSqlLockMode::RowExclusive])]
    #[TestWith(['SHARE UPDATE EXCLUSIVE', PostgreSqlLockMode::ShareUpdateExclusive])]
    #[TestWith(['SHARE', PostgreSqlLockMode::Share])]
    #[TestWith(['SHARE ROW EXCLUSIVE', PostgreSqlLockMode::ShareRowExclusive])]
    #[TestWith(['EXCLUSIVE', PostgreSqlLockMode::Exclusive])]
    #[TestWith(['ACCESS EXCLUSIVE', PostgreSqlLockMode::AccessExclusive])]
    public function testBindsEachRelationConflictMode(string $sqlMode, PostgreSqlLockMode $mode): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('LOCK TABLE t IN ' . $sqlMode . ' MODE');
        self::assertInstanceOf(LockRelationsStatement::class, $statement);
        self::assertSame($mode, $statement->mode);
        self::assertSame('LOCK TABLE "public"."t" IN ' . $sqlMode . ' MODE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
