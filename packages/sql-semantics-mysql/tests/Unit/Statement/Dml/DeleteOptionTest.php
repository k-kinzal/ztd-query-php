<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption;

#[CoversClass(DeleteOption::class)]
#[Small]
final class DeleteOptionTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['LOW_PRIORITY', 'QUICK', 'IGNORE'], array_map(static fn (DeleteOption $option): string => $option->value, DeleteOption::cases()));
    }
}
