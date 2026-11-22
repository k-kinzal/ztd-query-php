<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Transaction\TransactionCommand;

#[CoversClass(TransactionCommand::class)]
#[Small]
final class TransactionCommandTest extends TestCase
{
    public function testDefinesTheSixNativeTransitions(): void
    {
        self::assertSame(
            ['begin', 'commit', 'rollback', 'savepoint', 'release', 'rollback-to'],
            array_map(static fn (TransactionCommand $command): string => $command->value, TransactionCommand::cases()),
        );
    }
}
