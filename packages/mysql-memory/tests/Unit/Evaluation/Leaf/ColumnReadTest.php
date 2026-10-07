<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnRead::class)]
#[Small]
final class ColumnReadTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheColumn(): void
    {
        $domain = Domain::double();

        self::assertSame($domain, (new ColumnRead($domain, 3))->domain());
    }

    public function testEvaluateReadsThePositionOfTheCurrentRow(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [1, 'b', null]);

        self::assertSame(['b', null], [(new ColumnRead(Domain::integer(), 1))->evaluate($frame), (new ColumnRead(Domain::integer(), 2))->evaluate($frame)]);
    }

    public function testEvaluateReadsTheRowOfAnEnclosingBlock(): void
    {
        $session = (new Instance())->connect();
        $outer = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [10, 20]);
        $frame = new Frame($outer->context, [1], $outer);

        self::assertSame(20, (new ColumnRead(Domain::integer(), 1, 1))->evaluate($frame));
    }

    public function testEvaluateAnswersNullForAPositionTheRowDoesNotHave(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [1]);

        self::assertNull((new ColumnRead(Domain::integer(), 4))->evaluate($frame));
    }
}
