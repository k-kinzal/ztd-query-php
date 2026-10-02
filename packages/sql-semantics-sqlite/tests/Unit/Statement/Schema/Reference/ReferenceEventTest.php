<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceEvent;

#[CoversClass(ReferenceEvent::class)]
#[Small]
final class ReferenceEventTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEachEvent(): void
    {
        self::assertSame(['INSERT', 'DELETE', 'UPDATE'], array_column(ReferenceEvent::cases(), 'value'));
    }
}
