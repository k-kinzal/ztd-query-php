<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;

#[CoversClass(DeferredSelection::class)]
#[Medium]
final class DeferredSelectionTest extends TestCase
{
    public function testDeriveProgramRetainsOperandFactsInTheProgramScope(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SELECT (x,2)');
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredSelection::class, $operation->statement->body);
        $item = $operation->statement->body->items[0];
        self::assertInstanceOf(DeferredProjection::class, $item);
        self::assertTrue($operation->facts->covers($item->expression));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheInputAndPredicate(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1 FROM DUAL WHERE (2, 3)', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 FROM DUAL WHERE (2,3)')->toString());
    }
}
