<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;

#[CoversClass(WindowingLimit::class)]
#[Small]
final class WindowingLimitTest extends TestCase
{
    public function testCasesHoldTheWordsOfTheServer(): void
    {
        self::assertSame('GROUPS', WindowingLimit::GroupsUnit->value);
        self::assertCount(6, WindowingLimit::cases());
    }
}
