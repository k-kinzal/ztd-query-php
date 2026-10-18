<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Graph;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class LiteralKindTest extends TestCase
{
    public function testCapturedContract(): void
    {
        self::assertSame(['Constant', 'Array', 'Enum', 'Unresolved'], array_column(\Deriver\Deriver\Lowerer\Graph\LiteralKind::cases(), 'name'));
    }
}
