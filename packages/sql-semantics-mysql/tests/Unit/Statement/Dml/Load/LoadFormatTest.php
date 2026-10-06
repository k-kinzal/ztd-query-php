<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;

#[CoversClass(LoadFormat::class)]
#[Small]
final class LoadFormatTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['DATA', 'XML'], array_map(static fn (LoadFormat $format): string => $format->value, LoadFormat::cases()));
    }
}
