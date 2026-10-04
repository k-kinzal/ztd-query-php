<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\DefineChecks;

#[CoversClass(DefineChecks::class)]
#[Medium]
final class DefineChecksTest extends TestCase
{
    public function testDeriveAcceptsTheAlternativeSpellings(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE AGGREGATE a (basetype = int4, SFUNC1 = f, stype1 = int4)');
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveChecksOperators(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE OPERATOR + (rightarg = int4, procedure = f)');
        self::assertSame([], $operation->facts->diagnostics);
    }
}
