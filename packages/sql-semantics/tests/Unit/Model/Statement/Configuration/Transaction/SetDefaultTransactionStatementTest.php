<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Transaction\SetDefaultTransactionStatement;
use SqlSemantics\Model\Transaction\Access;
use SqlSemantics\Model\Transaction\Configuration\DefaultScope;
use SqlSemantics\Model\Transaction\Isolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetDefaultTransactionStatement::class)]
#[Medium]
final class SetDefaultTransactionStatementTest extends TestCase
{
    public function testWithOriginPreservesTheTransactionRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET SESSION TRANSACTION READ ONLY');
        self::assertInstanceOf(SetDefaultTransactionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET SESSION TRANSACTION READ ONLY');
        self::assertInstanceOf(SetDefaultTransactionStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite));
    }

    public function testWithScopeProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET SESSION TRANSACTION READ ONLY');
        self::assertInstanceOf(SetDefaultTransactionStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withScope(DefaultScope::Global);
        self::assertNotSame($statement, $copy);
        self::assertSame(DefaultScope::Global, $copy->scope);
        self::assertSame($original, $statement->toString());
    }

    public function testWithIsolationProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET SESSION TRANSACTION READ ONLY');
        self::assertInstanceOf(SetDefaultTransactionStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withIsolation(Isolation::Serializable);
        self::assertNotSame($statement, $copy);
        self::assertSame(Isolation::Serializable, $copy->isolation);
        self::assertSame($original, $statement->toString());
    }

    public function testWithAccessProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET SESSION TRANSACTION READ ONLY');
        self::assertInstanceOf(SetDefaultTransactionStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withAccess(Access::ReadWrite);
        self::assertNotSame($statement, $copy);
        self::assertSame(Access::ReadWrite, $copy->access);
        self::assertSame($original, $statement->toString());
    }

    public function testWithAccessCannotRemoveTheOnlyRequestedCharacteristic(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET SESSION TRANSACTION READ ONLY');
        self::assertInstanceOf(SetDefaultTransactionStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withAccess(null);
    }
}
