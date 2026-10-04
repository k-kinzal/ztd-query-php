<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTarget::class)]
#[Medium]
final class AlterTargetTest extends TestCase
{
    public function testKeywordsSpellTheKind(): void
    {
        self::assertSame([
          0 => 'FOREIGN',
          1 => 'TABLE',
        ], \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTarget::ForeignTable->keywords());
    }
}
