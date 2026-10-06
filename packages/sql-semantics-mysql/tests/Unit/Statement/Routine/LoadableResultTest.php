<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\LoadableResult;

#[CoversClass(LoadableResult::class)]
#[Small]
final class LoadableResultTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['STRING', 'REAL', 'DECIMAL', 'INTEGER'], array_map(static fn (LoadableResult $case): string => $case->value, LoadableResult::cases()));
    }
}
