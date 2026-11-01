<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem\Operand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(M\Query\Problem\Operand\RefusedQuery::class)]
#[Small]
final class RefusedQueryTest extends TestCase
{
    public function testMessageRetainsTheFullQuery(): void
    {
        $query = new M\Routine\Program\Selection\DeferredQuery(new M\Query\Select([], [new M\Query\Star()]));
        $problem = new M\Query\Problem\Operand\RefusedQuery($query);
        self::assertSame($query, $problem->query);
        self::assertSame('No tables used', $problem->message());
    }
}
