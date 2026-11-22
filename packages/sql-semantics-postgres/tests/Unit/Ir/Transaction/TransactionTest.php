<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Transaction\Transaction;
use SqlSemantics\Platform\PostgreSql\Ir\Transaction\TransactionCommand;

#[CoversClass(Transaction::class)]
#[Small]
final class TransactionTest extends TestCase
{
    public function testProfileIdIdentifiesTheNativeTransactionProfile(): void
    {
        $value = new Transaction(TransactionCommand::Begin);

        self::assertStringStartsWith('pg-17.2/', $value->profileId());
    }

    public function testToStringQuotesACheckpointName(): void
    {
        $value = new Transaction(TransactionCommand::RollbackTo, new Identifier('before work'));

        self::assertSame('ROLLBACK TO SAVEPOINT "before work"', $value->toString());
    }
}
