<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;

#[CoversClass(DeferredEvaluation::class)]
#[Medium]
final class DeferredEvaluationTest extends TestCase
{
    public function testDeriveProgramRecordsTheDeferredExpansionProblem(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() DO *');

        self::assertSame('No tables used', $operation->diagnostics()[0]->message());
    }

    public function testProblemIdentifiesTheFirstWildcard(): void
    {
        self::assertSame('No tables used', (new DeferredEvaluation([new Star()]))->problem()->message());
    }

    public function testRenderRetainsTheStoredInstruction(): void
    {
        self::assertSame('CREATE PROCEDURE p() DO t.*, TRUE', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() DO t.*, TRUE')->toString());
    }
}
