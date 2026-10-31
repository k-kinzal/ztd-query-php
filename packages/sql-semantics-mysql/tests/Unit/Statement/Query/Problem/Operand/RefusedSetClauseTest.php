<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem\Operand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSetClause;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(RefusedSetClause::class)]
#[Small]
final class RefusedSetClauseTest extends TestCase
{
    public function testMessageNamesTheRetainedClause(): void
    {
        $select = new Select([], [new SelectExpression(new NumberLiteral('1'))], limit: new RowLimit(new NumberLiteral('2')));
        $condition = new RefusedSetClause($select);
        self::assertSame($select, $condition->operand);
        self::assertSame('Incorrect usage of UNION and LIMIT', $condition->message());
    }

    public function testRefusesAnOperandWithNoConflictingClause(): void
    {
        $this->expectExceptionMessage('A refused set clause retains an ordering, limit or result analysis.');
        new RefusedSetClause(new Select([], [new SelectExpression(new NumberLiteral('1'))]));
    }
}
