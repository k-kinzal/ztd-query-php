<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection;

#[CoversClass(IndexDirection::class)]
#[Small]
final class IndexDirectionTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['FIRST', 'NEXT', 'PREV', 'LAST'], array_map(static fn (IndexDirection $direction): string => $direction->value, IndexDirection::cases()));
    }
}
