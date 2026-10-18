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
}
