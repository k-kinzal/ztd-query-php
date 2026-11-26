<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Transaction\TransactionCommand;

#[CoversClass(TransactionCommand::class)]
#[Small]
final class TransactionCommandTest extends TestCase
{
    public function testModesAndCheckpointOperationsAreDistinct(): void
    {
        self::assertSame([
            'BEGIN DEFERRED',
            'BEGIN IMMEDIATE',
            'BEGIN EXCLUSIVE',
            'COMMIT',
            'ROLLBACK',
            'SAVEPOINT',
            'RELEASE SAVEPOINT',
            'ROLLBACK TO SAVEPOINT',
        ], array_column(TransactionCommand::cases(), 'value'));
    }
}
