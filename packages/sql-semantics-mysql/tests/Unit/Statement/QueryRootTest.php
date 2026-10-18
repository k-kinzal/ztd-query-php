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
use SqlSemantics\Statement\Operation;

#[CoversClass(QueryRoot::class)]
#[Medium]
final class QueryRootTest extends TestCase
{
    public function testDeriveStatementPublishesTheQueryOutput(): void
    {
        $body = new ValuesQuery([new ValueRow([new NumberLiteral('1')])]);
        $root = new QueryRoot($body);
        $operation = new Operation((new Semantics(Dialect::MySql))->context(), $root);

        self::assertSame($operation->facts->query($body), $operation->facts->output);
        self::assertSame('column_0', $operation->facts->output->fields()?->at(0)->name?->value);
    }

    public function testInputDelegatesToTheQueryBody(): void
    {
        $table = new TableReference(new QualifiedName(new Name('t')));
        $root = new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('1'))], $table));

        self::assertSame($table, $root->input());
        self::assertNull((new QueryRoot(new ValuesQuery([new ValueRow([new NumberLiteral('1')])])))->input());
    }

    public function testRenderRefusesADeferredSortInAReadRoot(): void
    {
        $order = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem(new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $body = new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(null, new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(new Select([], [new SelectExpression(new NumberLiteral('1'))]), \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, new Select([], [new SelectExpression(new NumberLiteral('2'))])), [$order]);
        $this->expectExceptionMessage('A read statement has coherent fixed output columns.');
        new QueryRoot($body);
    }

    public function testRenderRetainsTheQueryBody(): void
    {
        $root = new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('1'))]));
        $operation = new Operation((new Semantics(Dialect::MySql))->context(), $root);

        self::assertSame('SELECT 1', $operation->toString());
        self::assertSame($root, $operation->statement);
    }
}
