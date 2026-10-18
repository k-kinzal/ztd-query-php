<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\GeneratedInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GeneratedInput::class)]
#[Small]
final class GeneratedInputTest extends TestCase
{
    public function testErrorPreservesTheVersionedFunctionDiagnostic(): void
    {
        $problem = new RefusedOperand(new Name('g'), OperandKind::FunctionCall, 'user');
        self::assertSame(3763, (new GeneratedInput())->error($problem, (new Instance())->connect())->getCode());
        self::assertSame(3102, (new GeneratedInput())->error($problem, (new Instance('5.7.44'))->connect())->getCode());
    }
}
