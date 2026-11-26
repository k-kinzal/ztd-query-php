<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\SavepointName;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\Transaction;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\TransactionCommand;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\TransactionLowering;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TransactionLowering::class)]
#[Small]
final class TransactionLoweringTest extends TestCase
{
    public function testLowerUsesTheEffectiveBeginMode(): void
    {
        $operation = (new Inspection())->inspect('BEGIN TRANSACTION ignored');

        self::assertEquals(
            new Transaction(TransactionCommand::BeginDeferred),
            (new TransactionLowering())->lower($operation->statement)
        );
    }

    public function testAnalyzeAllPublishesClosedTransactionCommands(): void
    {
        $semantics = new Semantics();
        $actual = $semantics->analyzeAll('BEGIN TRANSACTION unused; SAVEPOINT s; ROLLBACK TO s; END');

        self::assertEquals([
            new Transaction(TransactionCommand::BeginDeferred),
            new Transaction(TransactionCommand::Savepoint, new SavepointName('s')),
            new Transaction(TransactionCommand::RollbackTo, new SavepointName('s')),
            new Transaction(TransactionCommand::Commit),
        ], $actual);
    }
}
