<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Execution\TableLocks::class)]
#[Medium]
final class TableLocksTest extends TestCase
{
    public function testWriteUsesStructuredTargetsWithoutRetainingFormatting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('LOCK /* comment */ TABLE ONLY(t) IN SHARE MODE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Locking\LockRelationsStatement::class, $statement);
        $changed = $statement->withMode(\SqlSemantics\Model\Locking\PostgreSqlLockMode::RowExclusive);
        self::assertSame('LOCK TABLE ONLY "public"."t" IN ROW EXCLUSIVE MODE', $changed->toString());
        self::assertSame('LOCK /* comment */ TABLE ONLY(t) IN SHARE MODE', $statement->source->toString());
    }
}
