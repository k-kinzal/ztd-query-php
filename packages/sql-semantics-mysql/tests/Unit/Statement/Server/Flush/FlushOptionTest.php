<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Flush;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption;

#[CoversClass(FlushOption::class)]
#[Small]
final class FlushOptionTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['ERROR LOGS', 'ENGINE LOGS', 'GENERAL LOGS', 'SLOW LOGS', 'BINARY LOGS', 'RELAY LOGS', 'QUERY CACHE', 'HOSTS', 'PRIVILEGES', 'LOGS', 'STATUS', 'DES_KEY_FILE', 'USER_RESOURCES', 'OPTIMIZER_COSTS'], array_column(FlushOption::cases(), 'value'));
    }
}
