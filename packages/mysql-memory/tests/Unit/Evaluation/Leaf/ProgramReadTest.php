<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ProgramRead;
use MySqlMemory\Instance;
use MySqlMemory\Program\Variable;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProgramRead::class)]
#[Small]
final class ProgramReadTest extends TestCase
{
    public function testDomainAnswersTheDeclaredTypeThatCanBeNull(): void
    {
        self::assertTrue((new ProgramRead(new Variable('v', Domain::integer()->withNullable(false))))->domain()->nullable);
    }

    public function testEvaluateReadsTheValueTheVariableHoldsNow(): void
    {
        $variable = new Variable('v', Domain::integer(), 1);
        $read = new ProgramRead($variable);
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), (new Instance())->connect()->variables, 0.0));
        $variable->value = 2;

        self::assertSame(2, $read->evaluate($frame));
    }
}
