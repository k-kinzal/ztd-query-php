<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand;

#[CoversClass(RefusedKillOperand::class)]
#[Medium]
final class RefusedKillOperandTest extends TestCase
{
    public function testMessageDescribesTheRequiredProcessRole(): void
    {
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
        $failure = new RefusedKillOperand($row);
        self::assertSame($row, $failure->process);
        self::assertSame('A standalone KILL requires a scalar process identifier.', $failure->message());
    }
}
