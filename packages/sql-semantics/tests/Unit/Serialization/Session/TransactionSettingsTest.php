<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Transaction as Statement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Session\TransactionSettings;

#[CoversClass(TransactionSettings::class)]
#[Medium]
final class TransactionSettingsTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Model\BoundStatement> $expected
     */
    #[DataProvider('providerRequests')]
    public function testWritePreservesTheRequestedScopeAndOperands(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf($expected, $statement);
        $again = $binder->bind($statement->toString());
        self::assertInstanceOf($expected, $again);
        self::assertSame($statement->toString(), $again->toString());
    }

    /**
     * @return iterable<string, array{Dialect, string, class-string<\SqlSemantics\Model\BoundStatement>}>
     */
    public static function providerRequests(): iterable
    {
        yield 'one-shot' => [Dialect::MySql, 'SET TRANSACTION READ ONLY', Statement\SetNextTransactionStatement::class];
        yield 'access before isolation' => [Dialect::MySql, 'SET TRANSACTION READ ONLY, ISOLATION LEVEL SERIALIZABLE', Statement\SetNextTransactionStatement::class];
        yield 'session alias' => [Dialect::MySql, 'SET LOCAL TRANSACTION READ WRITE', Statement\SetDefaultTransactionStatement::class];
        yield 'persist' => [Dialect::MySql, 'SET PERSIST TRANSACTION ISOLATION LEVEL READ COMMITTED', Statement\SetDefaultTransactionStatement::class];
        yield 'persist-only' => [Dialect::MySql, 'SET PERSIST_ONLY TRANSACTION ISOLATION LEVEL SERIALIZABLE', Statement\SetDefaultTransactionStatement::class];
        yield 'current local' => [Dialect::PostgreSql, 'SET LOCAL TRANSACTION NOT DEFERRABLE, READ ONLY', Statement\SetCurrentTransactionStatement::class];
        yield 'session defaults' => [Dialect::PostgreSql, 'SET SESSION CHARACTERISTICS AS TRANSACTION DEFERRABLE', Statement\SetSessionTransactionStatement::class];
        yield 'temporary defaults' => [Dialect::PostgreSql, 'SET LOCAL SESSION CHARACTERISTICS AS TRANSACTION READ WRITE', Statement\SetSessionTransactionStatement::class];
        yield 'snapshot' => [Dialect::PostgreSql, "SET TRANSACTION SNAPSHOT 'snapshot-id'", Statement\SetTransactionSnapshotStatement::class];
    }

    #[TestWith(['SET TRANSACTION ISOLATION LEVEL SERIALIZABLE, READ ONLY', Statement\SetNextTransactionStatement::class, 'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE, READ ONLY'])]
    public function testWriteSpellsIsolationLevels(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, $statement->toString()]);
    }
}
