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
}
