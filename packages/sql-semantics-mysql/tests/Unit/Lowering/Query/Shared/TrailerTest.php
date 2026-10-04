<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Trailer::class)]
#[Small]
final class TrailerTest extends TestCase
{
    public function testEmptyTellsWhetherAClauseIsWritten(): void
    {
        self::assertTrue((new Trailer())->empty());
        self::assertFalse((new Trailer([], new RowLimit(new NumberLiteral('1'))))->empty());
    }

    public function testThenCombinesTheClausesOfBothTrailers(): void
    {
        $limit = new RowLimit(new NumberLiteral('1'));
        $lock = new LockingClause(LockStrength::Update);
        $combined = (new Trailer([], $limit))->then(new Trailer([], null, null, [$lock]));

        self::assertSame($limit, $combined->limit);
        self::assertSame([$lock], $combined->locking);
    }

    public function testThenRejectsAClauseWrittenTwice(): void
    {
        $this->expectException(AnalysisException::class);

        (new Trailer([new OrderItem(new ColumnUse(new Name('a')))]))->then(new Trailer([new OrderItem(new ColumnUse(new Name('b')))]));
    }

    public function testThenAccumulatesLockingClauses(): void
    {
        $share = new LockingClause(LockStrength::ShareMode);
        $update = new LockingClause(LockStrength::Update);

        self::assertSame([$share, $update], (new Trailer([], null, null, [$share]))->then(new Trailer([], null, null, [$update]))->locking);
    }

    public function testWrapPutsTheOrderingAndTheLockingAroundAQuery(): void
    {
        $query = new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))]));
        $wrapped = (new Trailer([], new RowLimit(new NumberLiteral('1')), null, [new LockingClause(LockStrength::Update)]))->wrap($query);

        self::assertInstanceOf(QueryStatement::class, $wrapped);
        self::assertInstanceOf(QueryExpression::class, $wrapped->query);
        self::assertSame($query, (new Trailer())->wrap($query));
    }

    public function testWrapRejectsProcedureAnalyse(): void
    {
        $this->expectException(AnalysisException::class);

        (new Trailer([], null, new ProcedureAnalyse()))->wrap(new ParenthesizedQuery(new Select([], [new SelectExpression(new NumberLiteral('1'))])));
    }
}
