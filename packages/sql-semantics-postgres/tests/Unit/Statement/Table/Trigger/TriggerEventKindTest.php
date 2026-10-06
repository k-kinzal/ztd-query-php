<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEventKind::class)]
#[Medium]
final class TriggerEventKindTest extends TestCase
{
    public function testCasesSpellTheEvents(): void
    {
        self::assertSame([
          0 => 'INSERT',
          1 => 'DELETE',
          2 => 'UPDATE',
          3 => 'TRUNCATE',
        ], array_map(static fn ($kind): string => $kind->value, \SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEventKind::cases()));
    }
}
