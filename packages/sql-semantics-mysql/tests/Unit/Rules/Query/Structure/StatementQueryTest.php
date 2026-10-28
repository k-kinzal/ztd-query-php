<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\StatementQuery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\LateOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;

#[CoversClass(StatementQuery::class)]
#[Small]
final class StatementQueryTest extends TestCase
{
    public function testProblemChecksTheCompleteDeferredBlock(): void
    {
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($row)], options: [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct], where: new NumberLiteral('1'));
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($selection);
        $rules = new StatementQuery();
        self::assertSame([$selection], $rules->blocks($query));
        self::assertNotNull($rules->problem($query));
        self::assertTrue($rules->requiresModern($query));
        $this->expectExceptionMessage('A statement query cannot combine ALL and DISTINCT in one query block.');
        $rules->check($query);
    }

    public function testProblemFindsContradictoryQuantifiersOnlyOnTheStatementSpine(): void
    {
        $query = new Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct], [new SelectExpression(new NumberLiteral('1'))]);
        $outer = new Select([], [new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery($query))]);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict::class, (new StatementQuery())->problem(new ParenthesizedQuery($query)));
        self::assertNull((new StatementQuery())->problem($outer));
    }

    public function testCheckRefusesContradictoryQuantifiersBeforeOperationConstruction(): void
    {
        $query = new Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct], [new SelectExpression(new NumberLiteral('1'))]);
        $this->expectExceptionMessage('A statement query cannot combine ALL and DISTINCT in one query block.');

        new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new ParenthesizedQuery($query));
    }

    public function testCheckRefusesProcedureWithoutFromOnlyInStatementPosition(): void
    {
        $query = new Select([], [new SelectExpression(new NumberLiteral('1'))], procedure: new ProcedureAnalyse());
        $this->expectExceptionMessage('A standalone PROCEDURE ANALYSE query requires a FROM clause.');

        (new StatementQuery())->check($query);
    }

    public function testCheckRefusesLateOrderingInStatementPosition(): void
    {
        $query = new Select([], [new SelectExpression(new NumberLiteral('1'))], limit: new RowLimit(new NumberLiteral('1')), late: new LateOrdering([], new RowLimit(new NumberLiteral('2'))));
        $this->expectExceptionMessage('An ORDER BY or LIMIT after the locking clauses or the LIMIT of a block is written in a subquery only.');

        (new StatementQuery())->check($query);
    }

    public function testRequiresModernDistinguishesUnfilteredAndFilteredBlocks(): void
    {
        $plain = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $filtered = new Select([], [new SelectExpression(new NumberLiteral('1'))], where: new NumberLiteral('0'));
        $rule = new StatementQuery();

        self::assertFalse($rule->requiresModern($plain));
        self::assertTrue($rule->requiresModern($filtered));
        self::assertTrue($rule->requiresModern(new SetOperation($plain, SetOperator::Union, null, $filtered)));
    }

    public function testBlocksUnwrapsEverySetArm(): void
    {
        $first = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $second = new Select([], [new SelectExpression(new NumberLiteral('2'))]);
        $query = new QueryExpression(null, new SetOperation(new ParenthesizedQuery($first), SetOperator::Union, null, $second), limit: new RowLimit(new NumberLiteral('3')));

        self::assertSame([$first, $second], (new StatementQuery())->blocks($query));
    }
}
