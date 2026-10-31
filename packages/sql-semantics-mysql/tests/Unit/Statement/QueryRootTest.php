<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(QueryRoot::class)]
#[Medium]
final class QueryRootTest extends TestCase
{
    public function testHasExecutionConditionKeepsAReadRequestAndItsDiagnostic(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $runnable = $semantics->analyze('SELECT 1');
        $refused = $semantics->analyze('SELECT *');
        self::assertInstanceOf(QueryRoot::class, $runnable);
        self::assertInstanceOf(QueryRoot::class, $refused);
        self::assertFalse($runnable->hasExecutionCondition());
        self::assertTrue($refused->hasExecutionCondition());
        self::assertCount(1, $refused->diagnostics());
        self::assertSame('No tables used', $refused->diagnostics()[0]->message());
        self::assertSame('SELECT *', $refused->toString());
    }

    public function testDeriveStatementPublishesTheQueryOutput(): void
    {
        $body = new ValuesQuery([new ValueRow([new NumberLiteral('1')])]);
        $root = new QueryRoot($body);
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new Semantics(Dialect::MySql))->context(), $root, new \SqlSemantics\Statement\Source\SourceMap());

        self::assertInstanceOf(QueryRoot::class, $operation);
        self::assertFalse($body->hasBinding());
        self::assertSame($operation->query->queryInformation(), $operation->output());
        self::assertSame('column_0', $operation->output()->fields()?->at(0)->name?->value);
    }

    public function testInputDelegatesToTheQueryBody(): void
    {
        $table = new TableReference(new QualifiedName(new Name('t')));
        $root = new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('1'))], $table));

        self::assertSame($table, $root->input());
        self::assertNull((new QueryRoot(new ValuesQuery([new ValueRow([new NumberLiteral('1')])])))->input());
    }

    public function testRenderRetainsADeferredSortInAReadRoot(): void
    {
        $order = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem(new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $body = new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(null, new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(new Select([], [new SelectExpression(new NumberLiteral('1'))]), \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, new Select([], [new SelectExpression(new NumberLiteral('2'))])), [$order]);
        self::assertTrue((new QueryRoot($body))->hasExecutionCondition());
    }

    public function testTheConstructorRetainsAPositionBeyondTheFixedProjection(): void
    {
        $query = new Select([], [new SelectExpression(new NumberLiteral('1'))], orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new NumberLiteral('2')))]);
        self::assertTrue((new QueryRoot($query))->hasExecutionCondition());
    }

    public function testTheConstructorRetainsZeroEvenWhenTheProjectionDependsOnTheCatalog(): void
    {
        $query = new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new TableReference(new QualifiedName(new Name('t'))), orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new NumberLiteral('0')))]);
        self::assertTrue((new QueryRoot($query))->hasExecutionCondition());
    }

    public function testTheConstructorRetainsAPositionBeyondADerivedStar(): void
    {
        $source = new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable(new Select([], [new SelectExpression(new NumberLiteral('1'))]), new Name('q'));
        $query = new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], $source, orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new NumberLiteral('2')))]);
        self::assertTrue((new QueryRoot($query))->hasExecutionCondition());
    }

    public function testTheConstructorRetainsAPositionBeyondACommonStar(): void
    {
        $with = new \SqlSemantics\Platform\MySql\Statement\Query\With\With(false, [new \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('1'))]))]);
        $body = new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new TableReference(new QualifiedName(new Name('c'))), orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new NumberLiteral('2')))]);
        self::assertTrue((new QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression($with, $body)))->hasExecutionCondition());
    }

    public function testRenderRetainsTheQueryBody(): void
    {
        $root = new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('1'))]));
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new Semantics(Dialect::MySql))->context(), $root, new \SqlSemantics\Statement\Source\SourceMap());

        self::assertSame('SELECT 1', $operation->toString());
        self::assertNotSame($root, $operation);
        self::assertFalse($root->hasBinding());
    }
}
