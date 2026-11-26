<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\SavepointName;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\Transaction;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\TransactionCommand;

#[CoversClass(Transaction::class)]
#[Small]
final class TransactionTest extends TestCase
{
    public function testToStringWritesTheCommandAndItsName(): void
    {
        $statement = new Transaction(TransactionCommand::RollbackTo, new SavepointName('has"quote'));

        self::assertSame('ROLLBACK TO SAVEPOINT "has""quote"', $statement->toString());
    }

    public function testProfileIdIdentifiesSQLite(): void
    {
        self::assertSame(
            'sqlite-3.47.2/utf8/short-column-names/numbered-parameters',
            (new Transaction(TransactionCommand::Commit))->profileId()
        );
    }
}
