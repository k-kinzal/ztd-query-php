<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\TransactionSettings;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Transaction as Statement;
use SqlSemantics\Model\Transaction\Access;
use SqlSemantics\Model\Transaction\Configuration\DefaultScope;
use SqlSemantics\Model\Transaction\Configuration\Deferrability;
use SqlSemantics\Model\Transaction\Configuration\Locality;
use SqlSemantics\Model\Transaction\Isolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TransactionSettings::class)]
#[Medium]
final class TransactionSettingsTest extends TestCase
{
    public function testBindRetainsIndependentTransactionCharacteristics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE, READ ONLY, DEFERRABLE');
        self::assertInstanceOf(Statement\SetCurrentTransactionStatement::class, $statement);
        self::assertSame([Isolation::Serializable, Access::ReadOnly, Deferrability::Deferrable], $statement->modes);
        self::assertSame(Locality::Session, $statement->locality);
    }

    #[DataProvider('providerMySqlVersions')]
    public function testBindRetainsMysqlNextTransactionLifetime(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ ONLY');
        self::assertInstanceOf(Statement\SetNextTransactionStatement::class, $statement);
        self::assertSame(Isolation::ReadCommitted, $statement->isolation);
        self::assertSame(Access::ReadOnly, $statement->access);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerMySqlVersions(): iterable
    {
        yield '5.6' => ['mysql-5.6.51'];
        yield '5.7' => ['mysql-5.7.44'];
        yield '8.0' => ['mysql-8.0.44'];
        yield '8.1' => ['mysql-8.1.0'];
        yield '8.2' => ['mysql-8.2.0'];
        yield '8.3' => ['mysql-8.3.0'];
        yield '8.4' => ['mysql-8.4.7'];
        yield '9.0' => ['mysql-9.0.1'];
        yield '9.1' => ['mysql-9.1.0'];
    }

    public function testBindRetainsIsolationAccessAndDeferrability(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET LOCAL SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY, NOT DEFERRABLE');
        self::assertInstanceOf(Statement\SetSessionTransactionStatement::class, $statement);
        self::assertSame([Isolation::RepeatableRead, Access::ReadOnly, Deferrability::NotDeferrable], $statement->modes);
        self::assertSame(Locality::Local, $statement->locality);
    }

    public function testBindRetainsGlobalMysqlIsolation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET GLOBAL TRANSACTION ISOLATION LEVEL READ COMMITTED, READ WRITE');
        self::assertInstanceOf(Statement\SetDefaultTransactionStatement::class, $statement);
        self::assertSame(DefaultScope::Global, $statement->scope);
        self::assertSame(Isolation::ReadCommitted, $statement->isolation);
        self::assertSame(Access::ReadWrite, $statement->access);
    }

    public function testBindKeepsPostgresRepeatedModesInTheirRequestedOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SET TRANSACTION READ ONLY READ WRITE READ ONLY');
        self::assertInstanceOf(Statement\SetCurrentTransactionStatement::class, $statement);
        self::assertSame([Access::ReadOnly, Access::ReadWrite, Access::ReadOnly], $statement->modes);
        self::assertSame('SET TRANSACTION READ ONLY, READ WRITE, READ ONLY', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testBindDistinguishesSnapshotImportFromCharacteristicChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET TRANSACTION SNAPSHOT '000003A1-1'");
        self::assertInstanceOf(Statement\SetTransactionSnapshotStatement::class, $statement);
        self::assertSame("'000003A1-1'", $statement->snapshot->text);
        self::assertSame(Locality::Session, $statement->locality);
    }

    public function testBindDoesNotClassifyAVariableNamedTransactionAsATransactionPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET @transaction = 'READ ONLY'", strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[0]);
    }

    #[TestWith(['set transaction isolation level read committed, read only', Statement\SetNextTransactionStatement::class, 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED, READ ONLY'])]
    #[TestWith(['set session transaction read write', Statement\SetDefaultTransactionStatement::class, 'SET SESSION TRANSACTION READ WRITE'])]
    #[TestWith(['SET @a = 1', \SqlSemantics\Model\Statement\Configuration\SetStatement::class, 'SET @`a` = 1'])]
    public function testBindReadsLowercaseMySqlModes(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }

    #[TestWith(['set transaction isolation level serializable, read only, deferrable', Statement\SetCurrentTransactionStatement::class, 'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE, READ ONLY, DEFERRABLE'])]
    #[TestWith(['SET SESSION CHARACTERISTICS AS TRANSACTION read write', Statement\SetSessionTransactionStatement::class, 'SET SESSION CHARACTERISTICS AS TRANSACTION READ WRITE'])]
    #[TestWith(['SET work_mem = \'1MB\'', \SqlSemantics\Model\Statement\Configuration\SetStatement::class, 'SET "work_mem" = \'1MB\''])]
    public function testBindReadsLowercasePostgreSqlModes(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
