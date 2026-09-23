<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Transaction\SetCurrentTransactionStatement;
use SqlSemantics\Model\Transaction\Access;
use SqlSemantics\Model\Transaction\Configuration\Deferrability;
use SqlSemantics\Model\Transaction\Configuration\Locality;
use SqlSemantics\Model\Transaction\Isolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetCurrentTransactionStatement::class)]
#[Medium]
final class SetCurrentTransactionStatementTest extends TestCase
{
    public function testWithOriginPreservesTheTransactionRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET TRANSACTION READ ONLY');
        self::assertInstanceOf(SetCurrentTransactionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET TRANSACTION READ ONLY');
        self::assertInstanceOf(SetCurrentTransactionStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite));
    }

    public function testWithModesProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET TRANSACTION READ ONLY');
        self::assertInstanceOf(SetCurrentTransactionStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withModes([Isolation::ReadCommitted, Access::ReadWrite, Deferrability::NotDeferrable]);
        self::assertNotSame($statement, $copy);
        self::assertSame([Isolation::ReadCommitted, Access::ReadWrite, Deferrability::NotDeferrable], $copy->modes);
        self::assertSame($original, $statement->toString());
    }

    public function testWithLocalityProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET TRANSACTION READ ONLY');
        self::assertInstanceOf(SetCurrentTransactionStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withLocality(Locality::Local);
        self::assertNotSame($statement, $copy);
        self::assertSame(Locality::Local, $copy->locality);
        self::assertSame($original, $statement->toString());
    }
}
