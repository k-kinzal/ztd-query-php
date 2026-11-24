<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee;

#[CoversClass(UniversalGrantee::class)]
final class UniversalGranteeTest extends TestCase
{
    public function testEveryoneUsesTheNativeToken(): void
    {
        self::assertSame('PUBLIC', UniversalGrantee::Everyone->value);
    }
}
