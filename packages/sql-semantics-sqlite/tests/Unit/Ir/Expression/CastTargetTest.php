<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;

#[CoversClass(CastTarget::class)]
#[Small]
final class CastTargetTest extends TestCase
{
    public function testEffectiveAffinityHasAStableSqlKeyword(): void
    {
        self::assertSame(['INTEGER', 'REAL', 'NUMERIC', 'TEXT', 'BLOB'], array_column(CastTarget::cases(), 'value'));
    }
}
