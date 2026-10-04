<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Weight;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;

#[CoversClass(WeightCast::class)]
#[Small]
final class WeightCastTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['CHAR', 'BINARY'], array_column(WeightCast::cases(), 'value'));
    }
}
