<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat;

#[CoversClass(TemporalFormat::class)]
#[Small]
final class TemporalFormatTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['DATE', 'TIME', 'TIMESTAMP', 'DATETIME'], array_column(TemporalFormat::cases(), 'value'));
    }
}
