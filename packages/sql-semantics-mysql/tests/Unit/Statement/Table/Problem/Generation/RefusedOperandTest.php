<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RefusedOperand::class)]
#[Small]
final class RefusedOperandTest extends TestCase
{
    public function testMessageDescribesTheOwningColumnAndFunction(): void
    {
        self::assertSame("Generated column 'g' excludes function user.", (new RefusedOperand(new Name('g'), OperandKind::FunctionCall, 'user'))->message());
    }

    public function testRejectsAFunctionRefusalWithoutAFunction(): void
    {
        $this->expectExceptionMessage('Only a function refusal names a nonempty function.');
        new RefusedOperand(new Name('g'), OperandKind::FunctionCall);
    }
}
