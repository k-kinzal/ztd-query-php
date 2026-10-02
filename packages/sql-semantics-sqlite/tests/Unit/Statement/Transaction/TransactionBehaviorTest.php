<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\TransactionBehavior;

#[CoversClass(TransactionBehavior::class)]
#[Small]
final class TransactionBehaviorTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEachBehavior(): void
    {
        self::assertSame(['DEFERRED', 'IMMEDIATE', 'EXCLUSIVE'], array_column(TransactionBehavior::cases(), 'value'));
    }
}
