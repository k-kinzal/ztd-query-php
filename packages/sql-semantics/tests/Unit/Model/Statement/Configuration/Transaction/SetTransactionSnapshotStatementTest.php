<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Transaction\SetTransactionSnapshotStatement;
use SqlSemantics\Model\Transaction\Configuration\Locality;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetTransactionSnapshotStatement::class)]
#[Medium]
final class SetTransactionSnapshotStatementTest extends TestCase
{
    public function testWithOriginPreservesTheTransactionRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET TRANSACTION SNAPSHOT 'snapshot-id'");
        self::assertInstanceOf(SetTransactionSnapshotStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET TRANSACTION SNAPSHOT 'snapshot-id'");
        self::assertInstanceOf(SetTransactionSnapshotStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite));
    }

    public function testWithSnapshotProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET TRANSACTION SNAPSHOT 'snapshot-id'");
        self::assertInstanceOf(SetTransactionSnapshotStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withSnapshot($statement->snapshot);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->snapshot->text, $copy->snapshot->text);
        self::assertSame($original, $statement->toString());
    }

    public function testWithLocalityProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET TRANSACTION SNAPSHOT 'snapshot-id'");
        self::assertInstanceOf(SetTransactionSnapshotStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withLocality(Locality::Local);
        self::assertNotSame($statement, $copy);
        self::assertSame(Locality::Local, $copy->locality);
        self::assertSame($original, $statement->toString());
    }
}
