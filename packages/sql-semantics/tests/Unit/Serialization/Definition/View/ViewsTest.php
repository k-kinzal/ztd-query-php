<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\View\MySqlViewProperties;
use SqlSemantics\Model\Definition\View\ViewAlgorithm;
use SqlSemantics\Model\Definition\View\ViewSecurity;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\CreateMaterializedViewStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\View\Views;

#[CoversClass(Views::class)]
#[Medium]
final class ViewsTest extends TestCase
{
    #[TestWith(['CREATE VIEW v AS SELECT 1'])]
    #[TestWith(['CREATE OR REPLACE TEMPORARY RECURSIVE VIEW "v"("n") WITH ("security_barrier", "check_option" = LOCAL) AS SELECT 1 WITH LOCAL CHECK OPTION'])]
    #[TestWith(['CREATE UNLOGGED MATERIALIZED VIEW IF NOT EXISTS "s"."m"("x") USING "heap" WITH ("fillfactor" = 70) TABLESPACE "ts" AS SELECT 1 WITH NO DATA'])]
    #[TestWith(['REFRESH MATERIALIZED VIEW CONCURRENTLY "s"."m"'])]
    #[TestWith(['REFRESH MATERIALIZED VIEW m WITH NO DATA'])]
    #[TestWith(['DROP MATERIALIZED VIEW IF EXISTS a, "s"."b" CASCADE'])]
    public function testWriteRetainsTheConcreteRequestAndOperands(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($statement->kind, $rebound->kind);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    #[TestWith(['DROP TABLE t'])]
    #[TestWith(['SELECT 1'])]
    public function testWriteReturnsNullForOtherSemanticFamilies(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id integer)'));
        self::assertNull(Views::write($binder->bind($sql)));
    }

    public function testViewWritesSqliteScopeAndExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE TEMP VIEW IF NOT EXISTS v (x) AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertSame('CREATE TEMPORARY VIEW IF NOT EXISTS "v"("x") AS SELECT 1', Views::view($statement)->toString());
    }

    public function testMysqlOmitsDefaultsAndWritesDeclaredProperties(): void
    {
        self::assertSame([], Views::mysql(new MySqlViewProperties(), Dialect::MySql));
        $written = Views::mysql(new MySqlViewProperties(ViewAlgorithm::TempTable, new AccountName('u', 'h'), ViewSecurity::Invoker), Dialect::MySql);
        self::assertSame('ALGORITHM = TEMPTABLE DEFINER = \'u\'@\'h\' SQL SECURITY INVOKER', implode(' ', array_map(static fn ($part): string => $part->toString(), $written)));
        $current = Views::mysql(new MySqlViewProperties(definer: CurrentAccount::Authenticated), Dialect::MySql);
        self::assertStringContainsString('CURRENT_USER', implode(' ', array_map(static fn ($part): string => $part->toString(), $current)));
    }

    public function testMysqlWritesEveryStatedSecurityOfAnAlteration(): void
    {
        self::assertSame(['SQL SECURITY DEFINER'], array_map(static fn ($part): string => $part->toString(), Views::mysql(new MySqlViewProperties(), Dialect::MySql, true)));
        self::assertSame([], Views::mysql(new MySqlViewProperties(security: null), Dialect::MySql, true));
        self::assertSame([], Views::mysql(new MySqlViewProperties(security: null), Dialect::MySql));
    }

    public function testMaterializedWritesOnlyDeclaredStorageClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE MATERIALIZED VIEW m AS SELECT 1');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        self::assertSame('CREATE MATERIALIZED VIEW "m" AS SELECT 1', Views::materialized($statement)->toString());
    }

}
