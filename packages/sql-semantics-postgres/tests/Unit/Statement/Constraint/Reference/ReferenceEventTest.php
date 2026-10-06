<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent::class)]
#[Medium]
final class ReferenceEventTest extends TestCase
{
    public function testCasesSpellTheEvents(): void
    {
        self::assertSame([
          0 => 'UPDATE',
          1 => 'DELETE',
        ], array_map(static fn ($event): string => $event->value, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent::cases()));
    }
}
