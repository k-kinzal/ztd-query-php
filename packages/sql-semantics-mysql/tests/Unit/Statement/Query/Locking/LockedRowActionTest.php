<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction;

#[CoversClass(LockedRowAction::class)]
#[Small]
final class LockedRowActionTest extends TestCase
{
    public function testCasesNameTheTwoActions(): void
    {
        self::assertSame(['Nowait', 'SkipLocked'], array_column(LockedRowAction::cases(), 'name'));
    }
}
