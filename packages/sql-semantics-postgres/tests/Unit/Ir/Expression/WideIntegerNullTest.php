<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerNull;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;

#[CoversClass(WideIntegerNull::class)]
#[Small]
final class WideIntegerNullTest extends TestCase
{
    public function testRendersNullWithItsDeclaredWidth(): void
    {
        self::assertSame('CAST(NULL AS pg_catalog.int8)', (new SelectSql())->integerOrNull(new WideIntegerNull(), null, 0, true));
    }
}
