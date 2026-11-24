<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceResetOperand;

#[CoversClass(SequenceResetOperand::class)]
final class SequenceResetOperandTest extends TestCase
{
    public function testKeepsTheDeclaredEnumCase(): void
    {
        self::assertSame('native-default', SequenceResetOperand::Native->value);
    }
}
