<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerTiming::class)]
#[Medium]
final class TriggerTimingTest extends TestCase
{
    public function testKeywordsSpellTheTiming(): void
    {
        self::assertSame([
          0 => 'INSTEAD',
          1 => 'OF',
        ], \SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerTiming::InsteadOf->keywords());
    }
}
