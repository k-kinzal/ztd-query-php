<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\RuleEvent::class)]
#[Medium]
final class RuleEventTest extends TestCase
{
    public function testCasesSpellTheEvents(): void
    {
        self::assertSame([
          0 => 'SELECT',
          1 => 'INSERT',
          2 => 'UPDATE',
          3 => 'DELETE',
        ], array_map(static fn ($event): string => $event->value, \SqlSemantics\Platform\PostgreSql\Statement\Table\View\RuleEvent::cases()));
    }
}
