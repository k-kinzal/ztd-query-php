<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;

#[CoversClass(CountedList::class)]
#[Small]
final class CountedListTest extends TestCase
{
    public function testCasesHoldTheServerMessages(): void
    {
        self::assertSame('The used SELECT statements have a different number of columns', CountedList::SetOperands->value);
        self::assertCount(4, CountedList::cases());
    }
}
