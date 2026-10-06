<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\KeyMatch::class)]
#[Medium]
final class KeyMatchTest extends TestCase
{
    public function testCasesSpellTheMatchTypes(): void
    {
        self::assertSame([
          0 => 'FULL',
          1 => 'PARTIAL',
          2 => 'SIMPLE',
        ], array_map(static fn ($match): string => $match->value, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\KeyMatch::cases()));
    }
}
