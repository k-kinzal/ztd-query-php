<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind;

#[CoversClass(AccountOptionKind::class)]
#[Small]
final class AccountOptionKindTest extends TestCase
{
    public function testWordsSpellEveryOption(): void
    {
        self::assertSame(['PASSWORD', 'REQUIRE', 'CURRENT', 'OPTIONAL'], AccountOptionKind::RequireCurrentOptional->words());
        self::assertSame(['PASSWORD_LOCK_TIME'], AccountOptionKind::LockTime->words());
    }

    public function testNumberedTellsTheOptionsWithANumber(): void
    {
        self::assertSame(['ExpireInterval', 'HistoryCount', 'ReuseInterval', 'FailedLoginAttempts', 'LockTime'], array_column(array_values(array_filter(AccountOptionKind::cases(), static fn (AccountOptionKind $kind): bool => $kind->numbered())), 'name'));
    }

    public function testUnitIsDayForTheIntervals(): void
    {
        self::assertSame(['DAY', null], [AccountOptionKind::ExpireInterval->unit(), AccountOptionKind::HistoryCount->unit()]);
    }
}
