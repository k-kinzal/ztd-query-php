<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Evaluation\Window\EmissionAssignment::class)]
#[Small]
final class EmissionAssignmentTest extends TestCase
{
    public function testDomainKeepsTheResolvedAssignmentResultType(): void
    {
        $domain = \MySqlMemory\Typing\Domain::integer();
        $assignment = new \MySqlMemory\Evaluation\Leaf\Assignment('x', new \MySqlMemory\Evaluation\Leaf\Constant($domain, 7), $domain, $domain);

        self::assertSame($domain, (new \MySqlMemory\Evaluation\Window\EmissionAssignment($assignment))->domain());
    }

    public function testEvaluateReadsTheStoredValueWithoutPublishingIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @x=99');
        $frame = new \MySqlMemory\Evaluation\Frame(new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $domain = \MySqlMemory\Typing\Domain::integer();
        $assignment = new \MySqlMemory\Evaluation\Leaf\Assignment('x', new \MySqlMemory\Evaluation\Leaf\Constant($domain, 7), $domain, $domain);
        $read = new \MySqlMemory\Evaluation\Window\EmissionAssignment($assignment);

        self::assertSame(7, $read->evaluate($frame));
        self::assertSame(99, $session->variables->user('x')[0]);
        self::assertSame(7, $assignment->evaluate($frame));
        self::assertSame(7, $session->variables->user('x')[0]);
    }

}
