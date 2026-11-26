<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(QueryStatement::class)]
#[Medium]
final class QueryStatementTest extends TestCase
{
    public function testDeriveStatementRecordsTheRowsOfTheQuery(): void
    {
        $operation = (new Inspection())->diagnose('SELECT 1 UNION SELECT 2 FROM t INTO @x');

        self::assertInstanceOf(QueryStatement::class, $operation->statement);
        self::assertSame($operation->facts->query($operation->statement->query), $operation->facts->output);
    }

    public function testDeriveQueryDerivesTheTargetsAndReportsTheirCount(): void
    {
        $operation = (new Inspection())->diagnose('SELECT 1 UNION SELECT 2 FROM t FOR UPDATE INTO @x, @y');

        self::assertInstanceOf(QueryStatement::class, $operation->statement);
        self::assertSame(IntoPosition::AfterLocking, $operation->statement->intoPosition);
        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(CountMismatch::class, $operation->facts->diagnostics[0]);
        self::assertSame(CountedList::IntoVariables, $operation->facts->diagnostics[0]->list);
    }

    public function testRenderWritesTheClausesInWrittenOrder(): void
    {
        $semantics = new Inspection();

        self::assertSame('SELECT 1 UNION SELECT 2 FROM t INTO @x FOR UPDATE', $semantics->diagnose('select 1 union select 2 from t into @x for update')->toString());
        self::assertInstanceOf(QueryStatement::class, $semantics->diagnose('select 1 union select 2 from t into @x for update')->statement);
        self::assertSame('(SELECT 1) FOR SHARE INTO @x', $semantics->diagnose('(select 1) for share into @x')->toString());
    }

    public function testTheClausesOfASingleBlockAreRejected(): void
    {
        $this->expectExceptionMessage('The INTO and locking clauses of a single query block belong to the block.');

        new QueryStatement(new Select([], [new SelectExpression(new NumberLiteral('1'))]), [new LockingClause(LockStrength::Update)]);
    }

    public function testAnIntoAfterTheSelectListIsRejected(): void
    {
        $this->expectExceptionMessage('An INTO after a whole query is written after the query or after its locking clauses.');

        new QueryStatement(new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))])), [], new IntoVariables([new UserVariable(new Name('x'))]), IntoPosition::AfterItems);
    }
}
