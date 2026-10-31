<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\QueryMeaning;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(QueryMeaning::class)]
#[Small]
final class QueryMeaningTest extends TestCase
{
    public function testOfReportsConflictingQuantifiersWithoutPublishingAStatement(): void
    {
        $query = new M\Query\Select([M\Query\SelectOption::All, M\Query\SelectOption::Distinct], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $this->expectExceptionMessage('Incorrect usage of ALL and DISTINCT');

        (new QueryMeaning())->of($query);
    }

    public function testOfUsesTheSingleBlockInstructionWithoutARedundantQueryWrapper(): void
    {
        $selection = new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
        $body = new M\Query\With\DeferredBody($selection);
        self::assertSame($selection, (new QueryMeaning())->of($body));
        $this->expectExceptionMessage('A single deferred block is a DeferredSelection instruction directly.');
        new M\Routine\Program\Selection\DeferredQuery($body);
    }

    public function testOfAssignsTheReadOrStoredRoleWithoutLosingTheQuery(): void
    {
        $query = new M\Query\ValuesQuery([new M\Query\ValueRow([])]);
        $stored = (new QueryMeaning())->of($query);
        self::assertInstanceOf(M\Routine\Program\Selection\DeferredQuery::class, $stored);
        self::assertSame($query, $stored->query);
        self::assertInstanceOf(M\QueryRoot::class, (new QueryMeaning())->of(new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')])])));
    }

    public function testOfRefusesAStandaloneStarWithoutTablesAndStoresIt(): void
    {
        $query = new M\Query\Select([], [new M\Query\Star()]);
        self::assertInstanceOf(M\Routine\Program\Selection\DeferredQuery::class, (new QueryMeaning())->of($query, 8, true));
        try {
            (new QueryMeaning())->of($query, 8);
            self::fail('A star needs tables.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertSame('No tables used', $failure->problem->message());
            self::assertSame(8, $failure->end);
            self::assertFalse($failure->aborts);
        }
    }
}
