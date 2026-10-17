<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(M\Routine\Program\Expression\DeferredSubquery::class)]
#[Medium]
final class DeferredSubqueryTest extends TestCase
{
    public function testOperandsRetainsTheCompleteQueryDependency(): void
    {
        $query = new M\Query\ValuesQuery([new M\Query\ValueRow([])]);
        $operand = new M\Routine\Program\Expression\DeferredSubquery($query);
        self::assertSame($query, $operand->query);
        self::assertSame([], $operand->operands());
    }

    public function testDeriveScalarRetainsTheStoredQueryDependency(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT (VALUES ROW())');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(M\Routine\Program\DeferredSelection::class, $operation->statement->body);
        $projection = $operation->statement->body->items[0];
        self::assertInstanceOf(M\Routine\Program\DeferredProjection::class, $projection);
        self::assertInstanceOf(M\Routine\Program\Expression\DeferredSubquery::class, $projection->expression);
        self::assertNotEmpty($operation->facts->diagnostics);
    }

    public function testProblemIdentifiesTheMissingReadColumns(): void
    {
        $value = new M\Routine\Program\Expression\DeferredSubquery(new M\Query\ValuesQuery([new M\Query\ValueRow([])]));

        self::assertSame([], $value->operands());
        $problem = $value->problem();
        self::assertInstanceOf(M\Query\Problem\Misuse::class, $problem);
        self::assertSame(M\Query\Problem\MisuseRule::EmptyValuesRow, $problem->rule);
    }

    public function testRenderPreservesTheStoredSubquery(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT (VALUES ROW())', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT (VALUES ROW())')->toString());
    }
}
