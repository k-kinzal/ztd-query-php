<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege;

#[CoversClass(RoutineColumnPrivilege::class)]
#[Small]
final class RoutineColumnPrivilegeTest extends TestCase
{
    public function testMessageDescribesTheIntrinsicMismatch(): void
    {
        self::assertSame('Column privileges apply only to tables.', (new RoutineColumnPrivilege(12, false))->message());
    }
}
