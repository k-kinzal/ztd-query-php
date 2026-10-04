<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedActionKind::class)]
#[Medium]
final class NamedActionKindTest extends TestCase
{
    public function testKeywordsSpellTheAction(): void
    {
        self::assertSame([
          0 => 'ENABLE',
          1 => 'ALWAYS',
          2 => 'TRIGGER',
        ], \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedActionKind::EnableAlwaysTrigger->keywords());
    }
}
