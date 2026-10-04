<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\TrimSide;

#[CoversClass(TrimSide::class)]
#[Small]
final class TrimSideTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['LEADING', 'TRAILING', 'BOTH'], array_column(TrimSide::cases(), 'value'));
    }
}
