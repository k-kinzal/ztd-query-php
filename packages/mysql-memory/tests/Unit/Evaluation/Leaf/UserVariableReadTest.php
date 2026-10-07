<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\UserVariableRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(UserVariableRead::class)]
#[Small]
final class UserVariableReadTest extends TestCase
{
    public function testDomainAnswersTheDomainTheVariableHadWhenResolved(): void
    {
        $domain = Domain::integer();

        self::assertSame($domain, (new UserVariableRead('v', $domain))->domain());
    }

    public function testEvaluateAnswersTheValueAsHeldWhenTheKindIsUnchanged(): void
    {
        $session = (new Instance())->connect();
        $session->variables->assign('v', 7, Domain::integer());

        self::assertSame(7, (new UserVariableRead('V', Domain::integer()))->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }

    public function testEvaluateAnswersNullForAVariableNeverAssigned(): void
    {
        $session = (new Instance())->connect();

        self::assertNull((new UserVariableRead('nothing', Domain::integer()))->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }

    public function testEvaluateConvertsAValueAssignedSinceTheStatementWasResolved(): void
    {
        $session = (new Instance())->connect();
        $session->variables->assign('v', '12abc', Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')));
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(
            [12, '12', 12.0],
            [(new UserVariableRead('v', Domain::integer()))->evaluate($frame), (new UserVariableRead('v', Domain::decimal(65, 0)))->evaluate($frame), (new UserVariableRead('v', Domain::double()))->evaluate($frame)],
        );
    }

    public function testEvaluateWritesANumberAsTextForAStringDomain(): void
    {
        $session = (new Instance())->connect();
        $session->variables->assign('v', 1.5, Domain::double());

        self::assertSame('1.5', (new UserVariableRead('v', Domain::string(0, Collation::binary())))->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }
}
