<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockWait::class)]
#[Small]
final class LockWaitTest extends TestCase
{
    public function testPoliciesAreSpelled(): void
    {
        self::assertSame(['NOWAIT', 'SKIP LOCKED'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockWait $wait): string => $wait->value, \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockWait::cases()));
    }
}
