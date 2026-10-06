<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;

#[CoversClass(CountingEdge::class)]
#[Small]
final class CountingEdgeTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['FROM FIRST', 'FROM LAST'], array_column(CountingEdge::cases(), 'value'));
    }
}
