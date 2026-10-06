<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachMode::class)]
#[Medium]
final class DetachModeTest extends TestCase
{
    public function testCasesSpellTheModes(): void
    {
        self::assertSame([
          0 => '',
          1 => 'CONCURRENTLY',
          2 => 'FINALIZE',
        ], array_map(static fn ($mode): string => $mode->value, \SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachMode::cases()));
    }
}
