<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceNumberKind::class)]
#[Medium]
final class SequenceNumberKindTest extends TestCase
{
    public function testCasesSpellTheOptions(): void
    {
        self::assertSame([
          0 => 'CACHE',
          1 => 'INCREMENT',
          2 => 'MAXVALUE',
          3 => 'MINVALUE',
          4 => 'START',
          5 => 'RESTART',
        ], array_map(static fn ($kind): string => $kind->value, \SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceNumberKind::cases()));
    }
}
