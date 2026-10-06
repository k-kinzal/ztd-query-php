<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnActionKind::class)]
#[Medium]
final class ColumnActionKindTest extends TestCase
{
    public function testConditionalIsTrueForIfExists(): void
    {
        self::assertSame([
          0 => false,
          1 => false,
          2 => false,
          3 => false,
          4 => true,
          5 => false,
          6 => true,
        ], array_map(static fn ($kind): bool => $kind->conditional(), \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnActionKind::cases()));
    }
}
