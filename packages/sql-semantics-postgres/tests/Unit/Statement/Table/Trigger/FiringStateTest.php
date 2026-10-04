<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\FiringState::class)]
#[Medium]
final class FiringStateTest extends TestCase
{
    public function testKeywordsSpellTheState(): void
    {
        self::assertSame([
          0 => 'ENABLE',
          1 => 'REPLICA',
        ], \SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\FiringState::EnableReplica->keywords());
    }
}
