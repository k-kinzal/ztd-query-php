<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\AggregateChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(AggregateChecks::class)]
#[Medium]
final class AggregateChecksTest extends TestCase
{
    public function testArgumentsChecksTheVariadicOrderedSet(): void
    {
        $semantics = new Inspection();
        $accepted = $semantics->diagnose('DROP AGGREGATE a(VARIADIC int4[] ORDER BY VARIADIC int4[])', []);
        $rejected = $semantics->diagnose('DROP AGGREGATE a(VARIADIC int4[] ORDER BY VARIADIC text[])', []);
        $counted = $semantics->diagnose('DROP AGGREGATE a(VARIADIC int4[] ORDER BY int4, int4)', []);
        self::assertSame([], $accepted->facts->diagnostics);
        self::assertEquals([new RoutineProblem(RoutineProblemKind::OrderedSetVariadic)], $rejected->facts->diagnostics);
        self::assertEquals([new RoutineProblem(RoutineProblemKind::OrderedSetVariadic)], $counted->facts->diagnostics);
    }
}
