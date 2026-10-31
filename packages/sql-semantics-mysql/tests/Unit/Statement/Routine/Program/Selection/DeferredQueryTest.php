<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Selection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(M\Routine\Program\Selection\DeferredQuery::class)]
#[Medium]
final class DeferredQueryTest extends TestCase
{
    public function testDeriveProgramRetainsEveryQueryDependency(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT missing UNION VALUES ROW()');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(M\Routine\Program\Selection\DeferredQuery::class, $operation->body);
        self::assertNotEmpty($operation->diagnostics());
    }

    public function testProblemIdentifiesTheReadColumnConstraint(): void
    {
        $query = new M\Routine\Program\Selection\DeferredQuery(new M\Query\ValuesQuery([new M\Query\ValueRow([])]));
        $problem = $query->problem();
        self::assertInstanceOf(M\Query\Problem\Misuse::class, $problem);
        self::assertSame(M\Query\Problem\MisuseRule::EmptyValuesRow, $problem->rule);
    }

    public function testRenderPreservesEveryStoredClause(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT missing UNION VALUES ROW()', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT missing UNION VALUES ROW()')->toString());
    }
}
