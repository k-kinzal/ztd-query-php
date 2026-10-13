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
