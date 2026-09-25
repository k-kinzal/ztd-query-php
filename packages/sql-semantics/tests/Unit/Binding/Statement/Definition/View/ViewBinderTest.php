<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\View\MySqlViewProperties;
use SqlSemantics\Model\Definition\View\PostgreSqlViewProperties;
use SqlSemantics\Model\Definition\View\ViewAlgorithm;
use SqlSemantics\Model\Definition\View\ViewSecurity;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\View\ViewBinder::class)]
#[Medium]
final class ViewBinderTest extends TestCase
{
    public function testBindReadsSqliteExistencePolicyAndTemporaryScope(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)')))->bind('CREATE TEMP VIEW IF NOT EXISTS v (x) AS SELECT a FROM t');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertTrue($statement->temporary);
        self::assertTrue($statement->ifNotExists);
        self::assertNull($statement->properties);
        self::assertSame('CREATE TEMPORARY VIEW IF NOT EXISTS "v"("x") AS SELECT "a" AS "a" FROM "main"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindReadsPostgreSqlRecursionOptionsAndCheck(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OR REPLACE RECURSIVE VIEW v (n) WITH (security_barrier) AS SELECT 1 WITH CASCADED CHECK OPTION');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertTrue($statement->replace);
        self::assertSame(ViewCheck::Cascaded, $statement->check);
        self::assertInstanceOf(PostgreSqlViewProperties::class, $statement->properties);
        self::assertTrue($statement->properties->recursive);
        self::assertSame('CREATE OR REPLACE RECURSIVE VIEW "v"("n") WITH ("security_barrier") AS SELECT 1 WITH CASCADED CHECK OPTION', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindAcceptsLegacyMySqlUnionDefinitions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t (a INT)')))->bind('CREATE VIEW v AS SELECT a FROM t UNION SELECT 1 ORDER BY 1 LIMIT 3');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertInstanceOf(CompoundStatement::class, $statement->query);
        self::assertSame('CREATE VIEW `v` AS SELECT `a` AS `a` FROM `t` UNION SELECT 1 ORDER BY 1 ASC LIMIT 3', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindLeavesMaterializedViewsToTheirOwnForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE MATERIALIZED VIEW m AS SELECT 1');
        self::assertNotInstanceOf(CreateViewStatement::class, $statement);
    }

    public function testColumnsAreReadFromTheDeclaredListOnly(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, b INT)'));
        $named = $binder->bind('CREATE VIEW v (x, y) AS SELECT a, b FROM t');
        $unnamed = $binder->bind('CREATE VIEW v AS SELECT a, b FROM t');
        self::assertInstanceOf(CreateViewStatement::class, $named);
        self::assertInstanceOf(CreateViewStatement::class, $unnamed);
        self::assertSame(['x', 'y'], $named->columns);
        self::assertSame([], $unnamed->columns);
    }

    public function testMysqlPropertiesAreReadWithTheirDefaults(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $plain = $binder->bind('CREATE VIEW v AS SELECT 1');
        $decorated = $binder->bind("CREATE ALGORITHM = MERGE DEFINER = 'u'@'h' SQL SECURITY INVOKER VIEW v AS SELECT 1");
        self::assertInstanceOf(CreateViewStatement::class, $plain);
        self::assertInstanceOf(CreateViewStatement::class, $decorated);
        self::assertInstanceOf(MySqlViewProperties::class, $plain->properties);
        self::assertSame(ViewAlgorithm::Undefined, $plain->properties->algorithm);
        self::assertNull($plain->properties->definer);
        self::assertSame(ViewSecurity::Definer, $plain->properties->security);
        self::assertInstanceOf(MySqlViewProperties::class, $decorated->properties);
        self::assertSame(ViewAlgorithm::Merge, $decorated->properties->algorithm);
        self::assertInstanceOf(AccountName::class, $decorated->properties->definer);
        self::assertSame(ViewSecurity::Invoker, $decorated->properties->security);
    }

    public function testBindReadsLowercasePostgreSqlModifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('create or replace temp recursive view v (n) as select 1 with local check option');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertTrue($statement->temporary);
        self::assertTrue($statement->replace);
        self::assertFalse($statement->ifNotExists);
        self::assertSame(ViewCheck::Local, $statement->check);
        self::assertInstanceOf(PostgreSqlViewProperties::class, $statement->properties);
        self::assertTrue($statement->properties->recursive);
        self::assertSame('CREATE OR REPLACE TEMPORARY RECURSIVE VIEW "v"("n") AS SELECT 1 WITH LOCAL CHECK OPTION', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE VIEW v AS SELECT recursive FROM t'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["CREATE VIEW v AS SELECT 'create temp x'"])]
    public function testBindReadsModifiersOnlyFromTheViewHeader(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(recursive INT)')))->bind($sql);
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertFalse($statement->temporary);
        self::assertInstanceOf(PostgreSqlViewProperties::class, $statement->properties);
        self::assertFalse($statement->properties->recursive);
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql])]
    public function testBindLeavesATableWithAViewAliasToItsOwnForm(Dialect $dialect): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('CREATE TABLE x AS SELECT 1 AS view');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateTableAsStatement::class, $statement);
    }

    public function testMysqlReadsLowercaseAlgorithmAndSecurity(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('create algorithm = merge sql security invoker view v as select 1 with local check option');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertSame(ViewCheck::Local, $statement->check);
        self::assertSame('CREATE ALGORITHM = MERGE SQL SECURITY INVOKER VIEW `v` AS SELECT 1 WITH LOCAL CHECK OPTION', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $source = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('create algorithm = temptable sql security definer view v as select 1'), ['create'])[0];
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::MySql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::MySql), ''));
        $properties = \SqlSemantics\Binding\Statement\Definition\View\ViewBinder::mysql($source, $context);
        self::assertSame(ViewAlgorithm::TempTable, $properties->algorithm);
        self::assertSame(ViewSecurity::Definer, $properties->security);
        self::assertNull($properties->definer);
    }

    public function testMysqlLeavesTheOmittedSecurityOfAnAlterationUnstated(): void
    {
        $source = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('ALTER VIEW v AS SELECT 1'), ['alter_view_stmt'])[0];
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::MySql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::MySql), ''));
        self::assertNull(\SqlSemantics\Binding\Statement\Definition\View\ViewBinder::mysql($source, $context, true)->security);
        self::assertSame(ViewSecurity::Definer, \SqlSemantics\Binding\Statement\Definition\View\ViewBinder::mysql($source, $context)->security);
    }


    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, null, 'CREATE VIEW v2 AS SELECT 1 AS materialized', 'CREATE VIEW "v2" AS SELECT 1 AS "materialized"'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, null, "CREATE VIEW v AS SELECT 'WITH CHECK OPTION', 'OR REPLACE'", 'CREATE VIEW "v" AS SELECT \'WITH CHECK OPTION\', \'OR REPLACE\''])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-5.7.44', "CREATE VIEW v AS SELECT 'WITH CHECK OPTION' AS a WITH LOCAL CHECK OPTION", "CREATE VIEW `v` AS SELECT 'WITH CHECK OPTION' AS `a` WITH LOCAL CHECK OPTION"])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-8.4.7', "CREATE VIEW v AS SELECT 'WITH CHECK OPTION' AS a", "CREATE VIEW `v` AS SELECT 'WITH CHECK OPTION' AS `a`"])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, null, "CREATE VIEW v AS SELECT 'OR REPLACE' AS materialized", 'CREATE VIEW "v" AS SELECT \'OR REPLACE\' AS "materialized"'])]
    public function testBindReadsTheViewFormFromItsGrammarNotFromTheQueryText(Dialect $dialect, ?string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-5.6.51', 'CREATE VIEW v AS SELECT a FROM t LOCK IN SHARE MODE', 'CREATE VIEW `v` AS SELECT `a` AS `a` FROM `t` LOCK IN SHARE MODE'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-5.7.44', 'CREATE VIEW v AS SELECT a FROM t FOR UPDATE', 'CREATE VIEW `v` AS SELECT `a` AS `a` FROM `t` FOR UPDATE'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-8.0.44', 'CREATE VIEW v AS SELECT a FROM t FOR UPDATE OF t NOWAIT', 'CREATE VIEW `v` AS SELECT `a` AS `a` FROM `t` FOR UPDATE OF `t` NOWAIT'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-8.4.7', 'CREATE OR REPLACE VIEW v AS SELECT a FROM t FOR UPDATE WITH CHECK OPTION', 'CREATE OR REPLACE VIEW `v` AS SELECT `a` AS `a` FROM `t` FOR UPDATE WITH CASCADED CHECK OPTION'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-9.1.0', 'CREATE VIEW v AS (SELECT a FROM t FOR SHARE SKIP LOCKED)', 'CREATE VIEW `v` AS SELECT `a` AS `a` FROM `t` FOR SHARE SKIP LOCKED'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, null, 'CREATE VIEW v AS SELECT a FROM t FOR UPDATE', 'CREATE VIEW "v" AS SELECT "a" AS "a" FROM "public"."t" FOR UPDATE'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, null, 'CREATE RECURSIVE VIEW v (a) AS SELECT a FROM t FOR KEY SHARE OF t', 'CREATE RECURSIVE VIEW "v"("a") AS SELECT "a" AS "a" FROM "public"."t" FOR KEY SHARE OF "t"'])]
    public function testBindKeepsTheLockingClauseOfTheViewQuery(Dialect $dialect, ?string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}
