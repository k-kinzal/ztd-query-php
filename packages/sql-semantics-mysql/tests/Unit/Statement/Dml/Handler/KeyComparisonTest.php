<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;

#[CoversClass(KeyComparison::class)]
#[Small]
final class KeyComparisonTest extends TestCase
{
    public function testCasesHoldTheirOperators(): void
    {
        self::assertSame(['=', '>=', '<=', '>', '<'], array_map(static fn (KeyComparison $comparison): string => $comparison->value, KeyComparison::cases()));
    }
}
