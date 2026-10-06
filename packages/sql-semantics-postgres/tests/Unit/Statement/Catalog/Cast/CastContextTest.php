<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CastContext::class)]
#[Medium]
final class CastContextTest extends TestCase
{
    public function testAssignmentIsKept(): void
    {
        self::assertSame('CREATE CAST (a AS b) WITH INOUT AS ASSIGNMENT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE CAST (a AS b) WITH INOUT AS ASSIGNMENT')->toString());
    }
}
