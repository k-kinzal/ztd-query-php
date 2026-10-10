<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Fact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Fact\Warning;

#[CoversClass(Warning::class)]
#[Medium]
final class WarningTest extends TestCase
{
    public function testMessageDescribesEachWarningInTheOrderItIsRaised(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT !a, BINARY b FROM t');

        self::assertSame(["'!' is deprecated and will be removed in a future release. Please use NOT instead", "'BINARY expr' is deprecated and will be removed in a future release. Please use CAST instead"], array_map(static fn (Warning $warning): string => $warning->message(), $operation->facts->warnings));
    }

    public function testWarningsAreKeptApartFromProblems(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT !a FROM t', []);

        self::assertCount(1, $operation->facts->warnings);
        self::assertCount(2, $operation->facts->diagnostics);
    }
}
