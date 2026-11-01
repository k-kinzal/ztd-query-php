<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedPurgeOperand;

#[CoversClass(RefusedPurgeOperand::class)]
#[Medium]
final class RefusedPurgeOperandTest extends TestCase
{
    public function testMessageDescribesTheRequiredCutoffRole(): void
    {
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
        $failure = new RefusedPurgeOperand($row);
        self::assertSame($row, $failure->moment);
        self::assertSame('A standalone PURGE BINARY LOGS BEFORE requires a scalar log cutoff operand.', $failure->message());
    }
}
