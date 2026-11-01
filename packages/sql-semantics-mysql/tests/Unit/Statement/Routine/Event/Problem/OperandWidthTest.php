<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Event\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth;

#[CoversClass(OperandWidth::class)]
#[Small]
final class OperandWidthTest extends TestCase
{
    public function testMessageRetainsTheWidthAndScheduleRole(): void
    {
        $problem = new OperandWidth(new OperandColumns(1, 2), Clause::Starts, false);
        self::assertSame('Operand should contain 1 column(s), not 2.', $problem->message());
        self::assertSame(Clause::Starts, $problem->clause);
        self::assertFalse($problem->rowValue);
    }
}
