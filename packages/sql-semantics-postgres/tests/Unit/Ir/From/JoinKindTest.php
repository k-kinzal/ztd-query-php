<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinKind;

#[CoversClass(JoinKind::class)]
#[Small]
final class JoinKindTest extends TestCase
{
    public function testOuterJoinRetainsItsDirection(): void
    {
        self::assertSame(JoinKind::Left, JoinKind::from('LEFT'));
        self::assertSame(JoinKind::Right, JoinKind::from('RIGHT'));
    }
}
