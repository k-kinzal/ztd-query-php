<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Evaluable::class)]
#[Small]
final class EvaluableTest extends TestCase
{
    public function testDomainAnswersTheResolvedDomainOfAnImplementation(): void
    {
        $domain = Domain::integer();
        $evaluable = new ColumnRead($domain, 0);

        self::assertSame($domain, $evaluable->domain());
    }

    public function testEvaluateComputesTheValueForTheRowOfAFrame(): void
    {
        $session = (new Instance())->connect();
        $evaluable = new ColumnRead(Domain::integer(), 1);

        self::assertSame(7, $evaluable->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [3, 7])));
    }
}
