<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName;

#[CoversClass(StatementItemName::class)]
#[Small]
final class StatementItemNameTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['NUMBER', 'ROW_COUNT'], array_map(static fn (StatementItemName $item): string => $item->value, StatementItemName::cases()));
    }
}
