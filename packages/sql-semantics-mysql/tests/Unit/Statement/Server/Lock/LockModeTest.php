<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode;

#[CoversClass(LockMode::class)]
#[Small]
final class LockModeTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['READ', 'READ LOCAL', 'WRITE', 'LOW_PRIORITY WRITE'], array_column(LockMode::cases(), 'value'));
    }
}
