<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StartTransaction::class)]
#[Medium]
final class StartTransactionTest extends TestCase
{
    public function testRenderWritesTheCharacteristicsInOrder(): void
    {
        self::assertSame('START TRANSACTION READ WRITE, WITH CONSISTENT SNAPSHOT, READ WRITE', (new Inspection())->inspect('start transaction read write, with consistent snapshot, read write')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('START TRANSACTION');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }

    public function testCharacteristicsAreKeptAsWritten(): void
    {
        self::assertSame([TransactionCharacteristic::ReadOnly], (new StartTransaction([TransactionCharacteristic::ReadOnly]))->characteristics);
    }
}
