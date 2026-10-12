<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ProjectedRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectedRead::class)]
#[Small]
final class ProjectedReadTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheColumn(): void
    {
        $domain = Domain::double();

        self::assertSame($domain, (new ProjectedRead($domain, 3))->domain());
    }

    public function testEvaluateReadsComputedFieldsIndependentlyFromInputColumns(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [99, 100]);
        $frame->projected = [4, null];

        self::assertSame(4, (new ProjectedRead(Domain::integer(), 0))->evaluate($frame));
        self::assertNull((new ProjectedRead(Domain::integer(), 1))->evaluate($frame));
    }
}
