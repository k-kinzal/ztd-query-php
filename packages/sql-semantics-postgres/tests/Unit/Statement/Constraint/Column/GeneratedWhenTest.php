<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\GeneratedWhen::class)]
#[Medium]
final class GeneratedWhenTest extends TestCase
{
    public function testKeywordsSpellTheChoice(): void
    {
        self::assertSame([
          0 => 'BY',
          1 => 'DEFAULT',
        ], \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\GeneratedWhen::ByDefault->keywords());
    }
}
