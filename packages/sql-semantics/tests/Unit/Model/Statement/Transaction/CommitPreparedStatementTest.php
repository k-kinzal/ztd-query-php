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
use SqlSemantics\Model\Statement\Transaction\CommitPreparedStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CommitPreparedStatement::class)]
#[Medium]
final class CommitPreparedStatementTest extends TestCase
{
    public function testBindsThePreparedTransactionIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMIT PREPARED 'tx1'");
        self::assertInstanceOf(CommitPreparedStatement::class, $statement);
        self::assertSame("'tx1'", $statement->transactionId->text);
        self::assertSame(StatementKind::Commit, $statement->kind);
        self::assertSame("COMMIT PREPARED 'tx1'", $statement->toString());
    }

    public function testWithOriginPreservesTheIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMIT PREPARED 'tx1'");
        self::assertInstanceOf(CommitPreparedStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::PostgreSql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->transactionId, $copy->transactionId);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
