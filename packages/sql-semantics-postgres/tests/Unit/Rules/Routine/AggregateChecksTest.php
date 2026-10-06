<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\AggregateChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;

#[CoversClass(AggregateChecks::class)]
#[Medium]
final class AggregateChecksTest extends TestCase
{
    public function testArgumentsChecksTheVariadicOrderedSet(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $accepted = $semantics->analyze('DROP AGGREGATE a(VARIADIC int4[] ORDER BY VARIADIC int4[])', []);
        $rejected = $semantics->analyze('DROP AGGREGATE a(VARIADIC int4[] ORDER BY VARIADIC text[])', []);
        $counted = $semantics->analyze('DROP AGGREGATE a(VARIADIC int4[] ORDER BY int4, int4)', []);
        self::assertSame([], $accepted->facts->diagnostics);
        self::assertEquals([new RoutineProblem(RoutineProblemKind::OrderedSetVariadic)], $rejected->facts->diagnostics);
        self::assertEquals([new RoutineProblem(RoutineProblemKind::OrderedSetVariadic)], $counted->facts->diagnostics);
    }
}
