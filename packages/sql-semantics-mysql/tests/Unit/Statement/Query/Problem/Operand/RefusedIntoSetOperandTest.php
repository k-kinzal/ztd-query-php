<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem\Operand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedIntoSetOperand;

#[CoversClass(RefusedIntoSetOperand::class)]
#[Medium]
final class RefusedIntoSetOperandTest extends TestCase
{
    public function testMessageDescribesTheRetainedDestinationRole(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 INTO @x')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $query->query);
        $problem = new RefusedIntoSetOperand($query->query, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union);
        self::assertSame('Incorrect usage of UNION and INTO', $problem->message());
    }
}
