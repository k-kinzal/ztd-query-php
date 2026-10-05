<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction;

#[CoversClass(HandlerAction::class)]
#[Small]
final class HandlerActionTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['CONTINUE', 'EXIT'], array_map(static fn (HandlerAction $action): string => $action->value, HandlerAction::cases()));
    }
}
