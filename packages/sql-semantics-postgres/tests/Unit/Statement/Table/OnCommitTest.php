<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\OnCommit::class)]
#[Medium]
final class OnCommitTest extends TestCase
{
    public function testKeywordsSpellTheAction(): void
    {
        self::assertSame([
          0 => 'DELETE',
          1 => 'ROWS',
        ], \SqlSemantics\Platform\PostgreSql\Statement\Table\OnCommit::DeleteRows->keywords());
    }
}
