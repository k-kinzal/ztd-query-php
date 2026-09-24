<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Write\InsertionPolicyBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InsertionPolicyBinder::class)]
#[Medium]
final class InsertionPolicyBinderTest extends TestCase
{
    public function testBindReadsSqliteConflictResolution(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)'));
        $replace = $binder->bind('INSERT OR REPLACE INTO t VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $replace);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\SqliteInsertion::class, $replace->policy);
        self::assertSame(\SqlSemantics\Model\Write\Policy\ConstraintResponse::Replace, $replace->policy->onViolation);
        self::assertSame(\SqlSemantics\Model\Write\InsertMode::Insert, $replace->mode);
        $plain = $binder->bind('REPLACE INTO t VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $plain);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\SqliteInsertion::class, $plain->policy);
        self::assertSame(\SqlSemantics\Model\Write\Policy\ConstraintResponse::Default, $plain->policy->onViolation);
        self::assertSame(\SqlSemantics\Model\Write\InsertMode::Replace, $plain->mode);
    }

    public function testBindReadsMySqlSchedulingAndIgnoreFromTheHeader(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)'));
        $delayed = $binder->bind('INSERT DELAYED IGNORE INTO t VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $delayed);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\MySqlInsertion::class, $delayed->policy);
        self::assertSame(\SqlSemantics\Model\Write\Policy\Scheduling::Delayed, $delayed->policy->scheduling);
        self::assertTrue($delayed->policy->ignore);
        self::assertSame('INSERT DELAYED IGNORE INTO `t` VALUES (1)', $delayed->toString());
        $plain = $binder->bind('INSERT INTO t VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $plain);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\MySqlInsertion::class, $plain->policy);
        self::assertSame(\SqlSemantics\Model\Write\Policy\Scheduling::Default, $plain->policy->scheduling);
        self::assertFalse($plain->policy->ignore);
    }

    public function testBindReadsPostgreSqlIdentityOverrides(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $system = $binder->bind('INSERT INTO t OVERRIDING SYSTEM VALUE VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $system);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\PostgreSqlInsertion::class, $system->policy);
        self::assertSame(\SqlSemantics\Model\Write\Policy\IdentityOverride::System, $system->policy->overriding);
        $user = $binder->bind('INSERT INTO t OVERRIDING USER VALUE SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertSelectStatement::class, $user);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\PostgreSqlInsertion::class, $user->policy);
        self::assertSame(\SqlSemantics\Model\Write\Policy\IdentityOverride::User, $user->policy->overriding);
        $plain = $binder->bind('INSERT INTO t VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $plain);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\PostgreSqlInsertion::class, $plain->policy);
        self::assertSame(\SqlSemantics\Model\Write\Policy\IdentityOverride::Default, $plain->policy->overriding);
    }

    public function testBindIgnoresModifiersAfterTheTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t(a INT, KEY k (a))')))->bind('insert into t select high_priority a from t ignore index (k)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertSelectStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\MySqlInsertion::class, $statement->policy);
        self::assertSame(\SqlSemantics\Model\Write\Policy\Scheduling::Default, $statement->policy->scheduling);
        self::assertFalse($statement->policy->ignore);
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'insert or replace into t values (1)', 'INSERT OR REPLACE INTO "main"."t" VALUES (1)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'insert into t overriding user value values (1)', 'INSERT INTO "public"."t" OVERRIDING USER VALUE VALUES (1)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'insert low_priority ignore into t values (1)', 'INSERT LOW_PRIORITY IGNORE INTO `t` VALUES (1)'])]
    public function testBindReadsLowerCasePolicies(Dialect $dialect, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(a INT)')))->bind($sql)->toString());
    }

    public function testBindAttachesTheMySqlRowAlias(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('INSERT INTO t VALUES (1) AS n(x)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\MySqlInsertion::class, $statement->policy);
        $alias = $statement->policy->rowAlias;
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\RowAlias::class, $alias);
        $policy = InsertionPolicyBinder::bind($statement->origin->source, Dialect::MySql, $alias);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\MySqlInsertion::class, $policy);
        self::assertSame($alias, $policy->rowAlias);
        self::assertSame(['x'], $policy->rowAlias->columns);
    }
}
