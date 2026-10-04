<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Limit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\CommaLimit::class)]
#[Small]
final class CommaLimitTest extends TestCase
{
    public function testRenderWritesTheComma(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 LIMIT ALL, 2');
        self::assertSame('SELECT 1 LIMIT ALL, 2', $query->toString());
    }
}
