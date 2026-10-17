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
    public function testOfAssignsTheReadOrStoredRoleWithoutLosingTheQuery(): void
    {
        $query = new M\Query\ValuesQuery([new M\Query\ValueRow([])]);
        $stored = (new QueryMeaning())->of($query);
        self::assertInstanceOf(M\Routine\Program\Selection\DeferredQuery::class, $stored);
        self::assertSame($query, $stored->query);
        self::assertInstanceOf(M\QueryRoot::class, (new QueryMeaning())->of(new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')])])));
    }
}
