<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Statement\Transaction\RollbackPreparedStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RollbackPreparedStatement::class)]
#[Medium]
final class RollbackPreparedStatementTest extends TestCase
{
    public function testBindsThePreparedTransactionIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ROLLBACK PREPARED 'tx1'");
        self::assertInstanceOf(RollbackPreparedStatement::class, $statement);
        self::assertSame("'tx1'", $statement->transactionId->text);
        self::assertSame(StatementKind::Rollback, $statement->kind);
        self::assertSame("ROLLBACK PREPARED 'tx1'", $statement->toString());
    }

    public function testWithOriginPreservesTheIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ROLLBACK PREPARED 'tx1'");
        self::assertInstanceOf(RollbackPreparedStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::PostgreSql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->transactionId, $copy->transactionId);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
