<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionMode::class)]
#[Small]
final class TransactionModeTest extends TestCase
{
    public function testParameterOfEachCharacteristic(): void
    {
        self::assertSame(['transaction_isolation', 'transaction_read_only', 'transaction_deferrable'], [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionMode::RepeatableRead->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionMode::ReadWrite->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionMode::NotDeferrable->parameter()]);
    }
}
