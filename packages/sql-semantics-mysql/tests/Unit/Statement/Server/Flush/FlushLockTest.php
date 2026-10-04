<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Flush;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushLock;

#[CoversClass(FlushLock::class)]
#[Small]
final class FlushLockTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['WITH READ LOCK', 'FOR EXPORT'], array_column(FlushLock::cases(), 'value'));
    }
}
