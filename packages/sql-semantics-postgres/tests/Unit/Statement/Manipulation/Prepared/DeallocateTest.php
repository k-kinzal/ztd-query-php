<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Deallocate::class)]
#[Medium]
final class DeallocateTest extends TestCase
{
    public function testDeriveStatementDerivesNothing(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DEALLOCATE p');
        self::assertNull($query->facts->output);
    }

    public function testRenderDropsTheIgnoredKeyword(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DEALLOCATE PREPARE p');
        self::assertSame('DEALLOCATE p', $query->toString());
    }
}
