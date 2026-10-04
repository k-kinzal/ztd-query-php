<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\Materialization::class)]
#[Small]
final class MaterializationTest extends TestCase
{
    public function testChoicesAreSpelled(): void
    {
        self::assertSame(['MATERIALIZED', 'NOT MATERIALIZED'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Query\With\Materialization $choice): string => $choice->value, \SqlSemantics\Platform\PostgreSql\Statement\Query\With\Materialization::cases()));
    }
}
