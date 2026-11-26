<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\RequestedPageSize;

#[CoversClass(RequestedPageSize::class)]
#[Small]
final class RequestedPageSizeTest extends TestCase
{
    public function testBytesKeepsAnInvalidPhysicalSizeAsTheNativeRequest(): void
    {
        self::assertSame(-2147483648, (new RequestedPageSize(-2147483648))->bytes);
        self::assertSame(724, (new RequestedPageSize(724))->bytes);
        self::assertSame(0, (new RequestedPageSize(0))->bytes);
    }
}
