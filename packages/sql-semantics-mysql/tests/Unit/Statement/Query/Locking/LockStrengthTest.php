<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;

#[CoversClass(LockStrength::class)]
#[Small]
final class LockStrengthTest extends TestCase
{
    public function testCasesNameTheThreeLocks(): void
    {
        self::assertSame(['Update', 'Share', 'ShareMode'], array_column(LockStrength::cases(), 'name'));
    }
}
