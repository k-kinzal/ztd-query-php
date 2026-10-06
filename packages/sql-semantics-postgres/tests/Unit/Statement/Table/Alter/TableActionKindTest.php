<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind::class)]
#[Medium]
final class TableActionKindTest extends TestCase
{
    public function testKeywordsSpellTheAction(): void
    {
        self::assertSame([
          0 => 'ENABLE',
          1 => 'ROW',
          2 => 'LEVEL',
          3 => 'SECURITY',
        ], \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind::EnableRowSecurity->keywords());
    }
}
