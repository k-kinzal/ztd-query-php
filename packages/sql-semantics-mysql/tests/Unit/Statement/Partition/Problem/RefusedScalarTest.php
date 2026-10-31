<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar;

#[CoversClass(RefusedScalar::class)]
#[Small]
final class RefusedScalarTest extends TestCase
{
    public function testMessagePreservesThePartitionRoleAndItsCompleteOperand(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $problem = new RefusedScalar($row);

        self::assertSame($row, $problem->operand);
        self::assertSame('This partition function is not allowed', $problem->message());
    }
}
