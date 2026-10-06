<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockStrength::class)]
#[Small]
final class LockStrengthTest extends TestCase
{
    public function testStrengthsAreSpelled(): void
    {
        self::assertSame(['UPDATE', 'NO KEY UPDATE', 'SHARE', 'KEY SHARE'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockStrength $strength): string => $strength->value, \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockStrength::cases()));
    }
}
