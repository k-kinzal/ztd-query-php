<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts::class)]
#[Medium]
final class ClauseFactsTest extends TestCase
{
    public function testDeriveRecordsTheModifiersOfAType(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER EXTENSION e ADD TYPE numeric(4, 2)')->facts->diagnostics);
    }
}
