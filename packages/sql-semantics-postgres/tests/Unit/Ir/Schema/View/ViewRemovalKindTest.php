<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewRemovalKind;

#[CoversClass(ViewRemovalKind::class)]
#[Medium]
final class ViewRemovalKindTest extends TestCase
{
    public function testKindsHaveDistinctNativeSpellings(): void
    {
        self::assertSame('VIEW', ViewRemovalKind::Ordinary->value);
        self::assertSame('MATERIALIZED VIEW', ViewRemovalKind::Materialized->value);
    }
}
