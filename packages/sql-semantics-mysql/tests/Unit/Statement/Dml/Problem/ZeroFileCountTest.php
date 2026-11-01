<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount;

#[CoversClass(ZeroFileCount::class)]
#[Small]
final class ZeroFileCountTest extends TestCase
{
    public function testMessagePreservesTheCountDomainAndNumericLocation(): void
    {
        $problem = new ZeroFileCount(24);
        self::assertSame('The number of files cannot be zero', $problem->message());
        self::assertSame(24, $problem->offset);
    }
}
