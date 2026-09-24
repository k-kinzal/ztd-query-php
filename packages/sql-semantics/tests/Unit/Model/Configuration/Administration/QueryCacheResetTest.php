<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Administration\QueryCacheReset;

#[CoversClass(QueryCacheReset::class)]
#[Small]
final class QueryCacheResetTest extends TestCase
{
    public function testAvailableInOnlyBeforeMySql8(): void
    {
        self::assertTrue((new QueryCacheReset())->availableIn(50651));
        self::assertFalse((new QueryCacheReset())->availableIn(80000));
    }
}
