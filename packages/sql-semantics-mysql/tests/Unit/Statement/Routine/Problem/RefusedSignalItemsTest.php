<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems;

#[CoversClass(RefusedSignalItems::class)]
#[Small]
final class RefusedSignalItemsTest extends TestCase
{
    public function testMessageKeepsTheOperandRefusal(): void
    {
        $problem = new RefusedSignalItems(new Resignal(null), new RefusedTemporalLiteral('bad'));

        self::assertSame('Incorrect DATE value.', $problem->message());
    }

    public function testDiagnosticRetainsARepeatedValueAndItsEarlierPrefix(): void
    {
        $item = new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem(\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::MysqlErrno, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
        $problem = new RefusedSignalItems(new Resignal(null, [$item]), $item);
        self::assertSame($item, $problem->problem);
        $diagnostic = $problem->diagnostic();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem::class, $diagnostic);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::DuplicateSignalItem, $diagnostic->rule);
        self::assertSame("Duplicate condition information item 'MYSQL_ERRNO'", $problem->message());
    }

    public function testRefusesARepetitionWithoutItsEarlierAssignment(): void
    {
        $item = new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem(\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName::MysqlErrno, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
        $this->expectExceptionMessage('A repeated condition item has an earlier assignment in the prefix.');
        new RefusedSignalItems(new Resignal(null), $item);
    }
}
