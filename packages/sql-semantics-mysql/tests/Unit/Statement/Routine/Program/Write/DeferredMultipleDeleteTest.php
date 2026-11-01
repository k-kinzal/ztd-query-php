<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete;

#[CoversClass(DeferredMultipleDelete::class)]
#[Medium]
final class DeferredMultipleDeleteTest extends TestCase
{
    public function testDeriveProgramRetainsDependenciesAndDiagnosticOwnership(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() DELETE q FROM (SELECT * FROM source) q');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        $delete = $operation->body;
        self::assertInstanceOf(DeferredMultipleDelete::class, $delete);
        self::assertTrue($delete->tables[0]->hasBinding());
        $problems = array_values(array_filter($operation->diagnostics(), static fn ($problem): bool => $problem instanceof M\Dml\Problem\WriteMisuse));
        self::assertCount(1, $problems);
        self::assertSame($delete->tables[0], $problems[0]->target);
    }

    public function testRenderRetainsTargetOrderPredicateAndModifiers(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() DELETE QUICK q FROM (SELECT 1 AS a) q WHERE q.a=1');
        self::assertSame('CREATE PROCEDURE p() DELETE QUICK q FROM (SELECT 1 AS a) q WHERE q.a = 1', $operation->toString());
    }
}
