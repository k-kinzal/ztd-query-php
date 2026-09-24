<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\PostgreSqlCommands;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\CreateDatabaseStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlCommands::class)]
#[Medium]
final class PostgreSqlCommandsTest extends TestCase
{
    #[TestWith(['CREATE DATABASE app', CreateDatabaseStatement::class])]
    public function testBindRoutesEachUtilityFamily(string $sql, string $class): void
    {
        self::assertSame($class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)::class);
    }

    public function testBindLeavesOtherDialectsToTheirBinders(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE DATABASE app');
        self::assertSame(\SqlSemantics\Model\Statement\Definition\MySql\CreateDatabaseStatement::class, $statement::class);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindRoutesEveryUtilityStatement')]
    public function testBindRoutesEveryUtilityStatement(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement::class . ' => ' . $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindRoutesEveryUtilityStatement(): iterable
    {
        return [
            'CREATE DATABASE app (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'CREATE DATABASE app', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Database\\CreateDatabaseStatement => CREATE DATABASE "app"'],
            'ALTER DATABASE app CONNECTION LIMIT 5 (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'ALTER DATABASE app CONNECTION LIMIT 5', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Database\\AlterDatabaseOptionsStatement => ALTER DATABASE "app" WITH CONNECTION LIMIT = 5'],
            'ALTER DATABASE app SET search_path = x (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'ALTER DATABASE app SET search_path = x', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Database\\AlterDatabaseSetStatement => ALTER DATABASE "app" SET "search_path" = "x"'],
            'DROP DATABASE app (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP DATABASE app', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Database\\DropDatabaseStatement => DROP DATABASE "app"'],
            'CREATE TABLESPACE ts LOCATION \'/x\' (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'CREATE TABLESPACE ts LOCATION \'/x\'', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Tablespace\\CreateTablespaceStatement => CREATE TABLESPACE "ts" LOCATION \'/x\''],
            'ALTER TABLESPACE ts RENAME TO ts2 (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'ALTER TABLESPACE ts RENAME TO ts2', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Catalog\\RenameObjectStatement => ALTER TABLESPACE "ts" RENAME TO "ts2"'],
            'DROP TABLESPACE ts (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP TABLESPACE ts', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Tablespace\\DropTablespaceStatement => DROP TABLESPACE "ts"'],
            'CREATE SCHEMA s (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'CREATE SCHEMA s', 'SqlSemantics\\Model\\Statement\\Definition\\PostgreSql\\Schema\\CreateSchemaStatement => CREATE SCHEMA "s"'],
            'ALTER SYSTEM SET work_mem = \'1MB\' (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'ALTER SYSTEM SET work_mem = \'1MB\'', 'SqlSemantics\\Model\\Statement\\Configuration\\System\\AlterSystemSetStatement => ALTER SYSTEM SET "work_mem" = \'1MB\''],
            'SHOW work_mem (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'SHOW work_mem', 'SqlSemantics\\Model\\Statement\\Configuration\\Show\\ShowSettingStatement => SHOW "work_mem"'],
            'SET CONSTRAINTS c IMMEDIATE (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'SET CONSTRAINTS c IMMEDIATE', 'SqlSemantics\\Model\\Statement\\Configuration\\SetNamedConstraintsStatement => SET CONSTRAINTS "c" IMMEDIATE'],
            'LOAD \'lib\' (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'LOAD \'lib\'', 'SqlSemantics\\Model\\Statement\\Loading\\LoadLibraryStatement => LOAD \'lib\''],
            'DO \'BEGIN END\' (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DO \'BEGIN END\'', 'SqlSemantics\\Model\\Statement\\Execution\\DoBlockStatement => DO \'BEGIN END\''],
            'CALL p(1) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'CALL p(1)', 'SqlSemantics\\Model\\Statement\\Procedural\\PostgreSql\\CallProcedureStatement => CALL "p"(1)'],
            'VACUUM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'VACUUM t', 'SqlSemantics\\Model\\Statement\\Maintenance\\PostgreSql\\VacuumStatement => VACUUM "public"."t"'],
            'ANALYZE t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'ANALYZE t', 'SqlSemantics\\Model\\Statement\\Maintenance\\PostgreSql\\AnalyzeStatement => ANALYZE "public"."t"'],
            'ANALYZE (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'ANALYZE', 'SqlSemantics\\Model\\Statement\\Maintenance\\PostgreSql\\AnalyzeStatement => ANALYZE'],
            'ANALYSE (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'ANALYSE', 'SqlSemantics\\Model\\Statement\\Maintenance\\PostgreSql\\AnalyzeStatement => ANALYZE'],
            'CLUSTER t USING i (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'CLUSTER t USING i', 'SqlSemantics\\Model\\Statement\\Maintenance\\PostgreSql\\ClusterTableStatement => CLUSTER "public"."t" USING "i"'],
            'COPY t TO STDOUT (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'COPY t TO STDOUT', 'SqlSemantics\\Model\\Statement\\Loading\\Copy\\CopyToStatement => COPY "public"."t" TO STDOUT'],
        ];
    }
}
