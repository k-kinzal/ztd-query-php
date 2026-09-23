<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Execution\Statements;

#[CoversClass(Statements::class)]
#[Medium]
final class StatementsTest extends TestCase
{
    public function testWriteLeavesQueriesForTheQuerySerializer(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(Statements::write($query));
    }

    public function testTransactionsKeepDistributedCommitDistinctFromLocalCommit(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $local = $binder->bind('COMMIT');
        $distributed = $binder->bind("XA COMMIT 'g' ONE PHASE");
        self::assertNotNull(Statements::transactions($local));
        self::assertNotNull(Statements::transactions($distributed));
        self::assertSame('COMMIT', $local->toString());
        self::assertSame("XA COMMIT 'g' ONE PHASE", $distributed->toString());
    }
}
