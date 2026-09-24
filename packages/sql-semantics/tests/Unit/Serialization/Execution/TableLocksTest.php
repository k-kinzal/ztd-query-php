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

    #[\PHPUnit\Framework\Attributes\TestWith(['lock tables t read, u as x write', 'LOCK TABLES `t` READ, `u` AS `x` WRITE'])]
    public function testWriteSpellsEveryLockForm(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind($sql)->toString());
    }
}
