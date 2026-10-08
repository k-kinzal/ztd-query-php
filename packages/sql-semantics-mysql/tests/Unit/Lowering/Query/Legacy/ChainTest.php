<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\Chain;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;

#[CoversClass(Chain::class)]
#[Small]
final class ChainTest extends TestCase
{
    public function testQueryLiftsTheClausesOfTheLastBlock(): void
    {
        $last = (new Block([], [new SelectExpression(new NumberLiteral('2'))]))->then(new Trailer([], new RowLimit(new NumberLiteral('1'))));
        $query = (new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()], [$last, new Trailer()]], [null], GrammarRelease::MySql5744))->query();

        self::assertInstanceOf(QueryExpression::class, $query);
        self::assertInstanceOf(SetOperation::class, $query->body);
        self::assertNotNull($query->limit);
    }

    public function testQueryOrdersAUnionAfterTheClausesOfItsLastBlock(): void
    {
        $last = (new Block([], [new SelectExpression(new NumberLiteral('2'))], null, new Dual()))->then(new Trailer([], null, null, [new LockingClause(LockStrength::Update)]));
        $query = (new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()], [$last, new Trailer([], new RowLimit(new NumberLiteral('1')))]], [null], GrammarRelease::MySql5651))->query();

        self::assertInstanceOf(OrderedSetOperation::class, $query);
        self::assertCount(1, $query->right->locking);
        self::assertNotNull($query->limit);
    }

    public function testEarlierRejectsALimitBeforeTheLastOperandIn57(): void
    {
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], [], GrammarRelease::MySql5744);

        $this->expectException(AnalysisException::class);

        $chain->earlier((new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], new RowLimit(new NumberLiteral('1')))), new Trailer(), 0);
    }

    public function testEarlierKeepsALimitBeforeTheLastOperandIn56(): void
    {
        $limit = new RowLimit(new NumberLiteral('1'));
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], [], GrammarRelease::MySql5651);
        $query = $chain->earlier((new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], $limit)), new Trailer(), 0);

        self::assertInstanceOf(Select::class, $query);
        self::assertSame($limit, $query->limit);
    }

    public function testEarlierRejectsAnOrderingAfterTheFirstOperandOfASubqueryUnion(): void
    {
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], [], GrammarRelease::MySql5651);

        $this->expectException(AnalysisException::class);

        $chain->earlier(new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer([new OrderItem(new NumberLiteral('1.5'))]), 0);
    }

    public function testEnclosedKeepsTheClausesAfterTheFirstParenthesizedOperand(): void
    {
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], [], GrammarRelease::MySql5651);
        $limited = $chain->enclosed(new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))])), new Trailer([], new RowLimit(new NumberLiteral('1'))), 0);
        $plain = new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))]));

        self::assertInstanceOf(QueryExpression::class, $limited);
        self::assertNotNull($limited->limit);
        self::assertSame($plain, $chain->enclosed($plain, new Trailer(), 2));
    }

    public function testEnclosedRejectsAnOrderingAfterAParenthesizedOperandThatLimitsItself(): void
    {
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], [], GrammarRelease::MySql5744);
        $limited = new Select([], [new SelectExpression(new NumberLiteral('1'))], null, null, null, null, [], null, [], new RowLimit(new NumberLiteral('1')));

        $this->expectException(AnalysisException::class);

        $chain->enclosed(new ParenthesizedQuery($limited), new Trailer([new OrderItem(new NumberLiteral('1.5'))]), 0);
    }

    public function testEnclosedRejectsClausesAfterALaterParenthesizedOperand(): void
    {
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], [], GrammarRelease::MySql5651);

        $this->expectException(AnalysisException::class);

        $chain->enclosed(new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))])), new Trailer([], new RowLimit(new NumberLiteral('1'))), 1);
    }

    public function testQueryKeepsTheOwnClausesOfAMiddleSelectInALeadingUnion(): void
    {
        $middle = (new Block([], [new SelectExpression(new NumberLiteral('2'))], null, new Dual()))->then(new Trailer([], null, null, [new LockingClause(LockStrength::Update)]));
        $query = (new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()], [$middle, new Trailer()], [new Block([], [new SelectExpression(new NumberLiteral('3'))]), new Trailer()]], [null, null], GrammarRelease::MySql5744))->query();

        self::assertInstanceOf(SetOperation::class, $query);
        self::assertInstanceOf(LeadingUnion::class, $query->left);
        self::assertCount(1, $query->left->right->locking);
    }

    public function testEarlierNamesTheClauseAUnionOperandMayNotWriteAsTheServerDoes(): void
    {
        $this->expectExceptionMessage('Incorrect usage of UNION and LIMIT: only the last SELECT of a union takes them without parentheses.');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 LIMIT 1 UNION SELECT 2');
    }

    public function testEarlierRefusesIntoBeforeTheOrderingOfAUnionOperand(): void
    {
        $this->expectExceptionMessage('Incorrect usage of UNION and INTO: only the last SELECT of a union takes INTO.');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 INTO @a FROM t ORDER BY 1 UNION SELECT 2');
    }

    public function testQueryRefusesProcedureAnalyseAfterAUnion(): void
    {
        $this->expectExceptionMessage('Incorrect usage of PROCEDURE and subquery: PROCEDURE ANALYSE follows a single query block only.');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 UNION SELECT 2 FROM t PROCEDURE ANALYSE()');
    }
}
