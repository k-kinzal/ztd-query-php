<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic;

#[CoversClass(StartTransaction::class)]
#[Medium]
final class StartTransactionTest extends TestCase
{
    public function testRenderWritesTheCharacteristicsInOrder(): void
    {
        self::assertSame('START TRANSACTION READ WRITE, WITH CONSISTENT SNAPSHOT, READ WRITE', (new Semantics(Dialect::MySql))->analyze('start transaction read write, with consistent snapshot, read write')->toString());
    }

    public function testDeriveStatementPublishesTheConcreteTransaction(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('START TRANSACTION');

        self::assertInstanceOf(StartTransaction::class, $operation);
        self::assertSame([], $operation->declarations());
    }

    public function testCharacteristicsAreKeptAsWritten(): void
    {
        self::assertSame([TransactionCharacteristic::ReadOnly], (new StartTransaction([TransactionCharacteristic::ReadOnly]))->characteristics);
    }

    public function testToStringReconstructsTheConcreteOperands(): void
    {
        self::assertSame('START TRANSACTION', (new StartTransaction())->toString());
    }

    public function testProfileRetainsTheSelectedRelease(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651);
        self::assertSame($profile, (new StartTransaction(profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new StartTransaction())->declarations());
    }
}
