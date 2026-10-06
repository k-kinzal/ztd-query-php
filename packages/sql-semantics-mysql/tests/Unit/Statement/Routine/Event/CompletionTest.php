<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion;

#[CoversClass(Completion::class)]
#[Small]
final class CompletionTest extends TestCase
{
    public function testCasesNamePreserveAndNotPreserve(): void
    {
        self::assertSame(['Preserve', 'NotPreserve'], array_map(static fn (Completion $completion): string => $completion->name, Completion::cases()));
    }
}
