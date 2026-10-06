<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOptionKind::class)]
#[Medium]
final class LikeOptionKindTest extends TestCase
{
    public function testCasesSpellTheProperties(): void
    {
        self::assertSame(10, count(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOptionKind::cases()));
    }
}
