<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\Transaction\SnapshotBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Transaction\SetTransactionSnapshotStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SnapshotBinder::class)]
#[Medium]
final class SnapshotBinderTest extends TestCase
{
    public function testBindRetainsTheConcreteRequestOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET TRANSACTION SNAPSHOT 'snapshot-id'", strict: false);
        self::assertInstanceOf(SetTransactionSnapshotStatement::class, $statement);
        self::assertSame("'snapshot-id'", $statement->snapshot->text);
    }
}
