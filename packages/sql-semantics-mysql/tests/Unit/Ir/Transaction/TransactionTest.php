<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Transaction\Transaction;
use SqlSemantics\Platform\MySql\Ir\Transaction\TransactionCommand;

#[CoversClass(Transaction::class)]
#[Small]
final class TransactionTest extends TestCase
{
    public function testProfileIdIdentifiesTheNativeTransactionProfile(): void
    {
        $value = new Transaction(TransactionCommand::Begin);

        self::assertStringStartsWith('mysql-9.1.0/', $value->profileId());
    }

    public function testToStringQuotesACheckpointName(): void
    {
        $value = new Transaction(TransactionCommand::RollbackTo, new Identifier('before work'));

        self::assertSame('ROLLBACK TO SAVEPOINT `before work`', $value->toString());
    }
}
