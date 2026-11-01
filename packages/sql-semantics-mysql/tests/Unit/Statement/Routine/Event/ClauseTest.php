<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause;

#[CoversClass(Clause::class)]
#[Small]
final class ClauseTest extends TestCase
{
    public function testCasesDistinguishTheFourOperandRoles(): void
    {
        self::assertSame(['AT', 'INTERVAL', 'STARTS', 'ENDS'], array_column(Clause::cases(), 'value'));
    }
}
