<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\AssignedConditionOperand;

#[CoversClass(AssignedConditionOperand::class)]
#[Small]
final class AssignedConditionOperandTest extends TestCase
{
    public function testMessageDescribesTheRoleAndRetainsTheAssignedExpression(): void
    {
        $assignment = new \SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('n')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('17'));
        $problem = new AssignedConditionOperand($assignment);
        self::assertSame($assignment, $problem->operand);
        self::assertSame('A condition operand reads a variable; it cannot assign one.', $problem->message());
    }
}
