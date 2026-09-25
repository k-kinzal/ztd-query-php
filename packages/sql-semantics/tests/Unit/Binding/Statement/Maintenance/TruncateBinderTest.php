<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Maintenance\TruncateBinder::class)]
#[Medium]
final class TruncateBinderTest extends TestCase
{
    public function testBindKeepsUnresolvedQualifiedTargetsForDiagnostics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('TRUNCATE ONLY app.missing', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\OnlyTableReference::class, $statement->tables[0]);
        self::assertSame(['app', 'missing'], $statement->tables[0]->name->parts);
        self::assertFalse($statement->tables[0]->declaration->resolved);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
    }

    #[TestWith([Dialect::PostgreSql, 'truncate t, u restart identity cascade', \SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement::class, 'TRUNCATE TABLE "public"."t", "public"."u" RESTART IDENTITY CASCADE'])]
    #[TestWith([Dialect::PostgreSql, 'TRUNCATE t', \SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement::class, 'TRUNCATE TABLE "public"."t" CONTINUE IDENTITY RESTRICT'])]
    #[TestWith([Dialect::PostgreSql, 'truncate table only t continue identity restrict', \SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement::class, 'TRUNCATE TABLE ONLY "public"."t" CONTINUE IDENTITY RESTRICT'])]
    #[TestWith([Dialect::MySql, 'truncate table t', \SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement::class, 'TRUNCATE TABLE `t`'])]
    #[TestWith([Dialect::MySql, 'TRUNCATE t', \SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement::class, 'TRUNCATE TABLE `t`'])]
    public function testBindSpellsEachDialectTruncation(Dialect $dialect, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind($sql);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
