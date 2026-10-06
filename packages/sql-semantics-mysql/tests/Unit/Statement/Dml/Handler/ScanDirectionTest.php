<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection;

#[CoversClass(ScanDirection::class)]
#[Small]
final class ScanDirectionTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['FIRST', 'NEXT'], array_map(static fn (ScanDirection $direction): string => $direction->value, ScanDirection::cases()));
    }
}
