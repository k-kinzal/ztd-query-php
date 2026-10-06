<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent;

#[CoversClass(ReferenceEvent::class)]
#[Small]
final class ReferenceEventTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('UPDATE', ReferenceEvent::Update->value);
    }
}
