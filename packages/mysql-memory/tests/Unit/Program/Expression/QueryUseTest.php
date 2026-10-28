<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\QueryUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(QueryUse::class)]
#[Small]
final class QueryUseTest extends TestCase
{
    public function testStatementPreparesTheRetainedQueryWithoutSqlInput(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $statement = new M\QueryRoot(new M\Query\Select([], [new M\Query\Star()]));
        $session->text = 'Unrelated query label';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1096);
        (new QueryUse())->statement($statement, $session);
    }

    public function testTemplatesPrepareInnerOperandsBeforeOuterColumns(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        $session->query('WITH c AS (SELECT ROW(1,2)) SELECT missing FROM c');
    }

    public function testPrepareReportsAnInsertOnlyDefaultBeforeCompilation(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3943);
        $session->query('SELECT (VALUES ROW(DEFAULT))');
    }

    /**
     * @return iterable<string, array{M\Routine\Program\Selection\DeferredQuery, int}>
     */
    public static function providerQueries(): iterable
    {
        $empty = new M\Query\ValuesQuery([new M\Query\ValueRow([])]);
        $unknown = new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('missing')))]);
        yield 'empty read' => [new M\Routine\Program\Selection\DeferredQuery($empty), 3942];
        yield 'earlier name' => [new M\Routine\Program\Selection\DeferredQuery(new M\Query\Set\SetOperation($unknown, M\Query\Set\SetOperator::Union, null, $empty)), 1054];
        yield 'later name' => [new M\Routine\Program\Selection\DeferredQuery(new M\Query\Set\SetOperation($empty, M\Query\Set\SetOperator::Union, null, $unknown)), 3942];
        $ordering = new M\Routine\Program\Selection\DeferredOrderItem(new M\Expression\Row([new M\Name\ColumnUse(new Name('v')), new M\Literal\NumberLiteral('2')]));
        yield 'ordering alias' => [new M\Routine\Program\Selection\DeferredQuery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('7'), new Name('v'))], orderBy: [$ordering])), 1241];
        yield 'tableless star' => [new M\Routine\Program\Selection\DeferredQuery(new M\Query\Select([], [new M\Query\Star()])), 1096];
    }

    #[DataProvider('providerQueries')]
    public function testRaisePreparesEveryDependencyWithoutInputText(M\Routine\Program\Selection\DeferredQuery $query, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'Unrelated request label';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new QueryUse())->raise($query, $session);
    }
    public function testPlanKeepsNamedFramesAfterMissingColumnResolution(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(x INT)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $session->query('SELECT FIRST_VALUE(id) OVER w FROM t WINDOW w AS (ROWS 1.5 PRECEDING)');
    }

}
