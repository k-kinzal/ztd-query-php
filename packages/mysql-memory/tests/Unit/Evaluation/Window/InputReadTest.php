<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Evaluation\Window\InputRead::class)]
#[Small]
final class InputReadTest extends TestCase
{
    public function testDomainKeepsTheDeclaredInputFieldType(): void
    {
        $domain = \MySqlMemory\Typing\Domain::integer();

        self::assertSame($domain, (new \MySqlMemory\Evaluation\Window\InputRead($domain, -1))->domain());
    }

    public function testEvaluateKeepsFieldsSeparateFromVariableAndOutputValues(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @x=99');
        $frame = new \MySqlMemory\Evaluation\Frame(new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [17]);
        $frame->windowSlots = [-1 => 7,0 => null];
        $argument = new \MySqlMemory\Evaluation\Window\InputRead(\MySqlMemory\Typing\Domain::integer(), -1);
        $output = new \MySqlMemory\Evaluation\Window\InputRead(\MySqlMemory\Typing\Domain::integer(), 0);

        self::assertSame(7, $argument->evaluate($frame));
        self::assertNull($output->evaluate($frame));
        self::assertSame(99, $session->variables->user('x')[0]);
    }

}
