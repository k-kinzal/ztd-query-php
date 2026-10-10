<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Outer;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Outer::class)]
#[Small]
final class OuterTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheInnerExpression(): void
    {
        $domain = Domain::double();

        self::assertSame($domain, (new Outer(new ColumnRead($domain, 0), 1))->domain());
    }

    public function testEvaluateEvaluatesTheExpressionInTheFrameOfItsBlock(): void
    {
        $session = (new Instance())->connect();
        $outermost = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), ['outermost']);
        $middle = new Frame($outermost->context, ['middle'], $outermost);
        $frame = new Frame($outermost->context, ['inner'], $middle);

        self::assertSame(['middle', 'outermost'], [(new Outer(new ColumnRead(Domain::integer(), 0), 1))->evaluate($frame), (new Outer(new ColumnRead(Domain::integer(), 0), 2))->evaluate($frame)]);
    }
}
