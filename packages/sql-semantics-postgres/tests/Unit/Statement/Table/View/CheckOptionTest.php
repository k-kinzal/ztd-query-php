<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CheckOption::class)]
#[Medium]
final class CheckOptionTest extends TestCase
{
    public function testCascadedIsFalseForLocal(): void
    {
        self::assertSame([
          0 => true,
          1 => true,
          2 => false,
        ], array_map(static fn ($option): bool => $option->cascaded(), \SqlSemantics\Platform\PostgreSql\Statement\Table\View\CheckOption::cases()));
    }

    public function testKeywordsSpellTheClause(): void
    {
        self::assertSame([
          0 => 'WITH',
          1 => 'CASCADED',
          2 => 'CHECK',
          3 => 'OPTION',
        ], \SqlSemantics\Platform\PostgreSql\Statement\Table\View\CheckOption::Cascaded->keywords());
    }
}
