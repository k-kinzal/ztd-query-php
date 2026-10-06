<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlagKind::class)]
#[Medium]
final class SequenceFlagKindTest extends TestCase
{
    public function testCasesSpellTheFlags(): void
    {
        self::assertSame(7, count(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\SequenceFlagKind::cases()));
    }
}
