<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic;

#[CoversClass(TransactionCharacteristic::class)]
#[Small]
final class TransactionCharacteristicTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['WITH CONSISTENT SNAPSHOT', 'READ ONLY', 'READ WRITE'], array_column(TransactionCharacteristic::cases(), 'value'));
    }
}
