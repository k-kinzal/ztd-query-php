<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexLock;

#[CoversClass(IndexLock::class)]
#[Small]
final class IndexLockTest extends TestCase
{
    public function testNativeNamesAreDistinct(): void
    {
        self::assertSame(['DEFAULT', 'NONE', 'SHARED', 'EXCLUSIVE'], array_column(IndexLock::cases(), 'value'));
    }
}
