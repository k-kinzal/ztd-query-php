<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Constant::class)]
#[Small]
final class ConstantTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheValue(): void
    {
        $domain = Domain::decimal(3, 2);

        self::assertSame($domain, (new Constant($domain, '1.50'))->domain());
    }

    public function testEvaluateAnswersTheValueWhateverTheRow(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [9]);

        self::assertSame(['1.50', null], [(new Constant(Domain::decimal(3, 2), '1.50'))->evaluate($frame), (new Constant(Domain::null(), null))->evaluate($frame)]);
    }
}
