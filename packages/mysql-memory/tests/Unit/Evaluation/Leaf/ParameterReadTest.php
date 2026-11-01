<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ParameterRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(ParameterRead::class)]
#[Small]
final class ParameterReadTest extends TestCase
{
    public function testDomainReturnsTheRetainedType(): void
    {
        $derived = Domain::decimal(65, 30);
        self::assertSame($derived, (new ParameterRead(1, Domain::integer(), $derived, true))->domain());
    }

    public function testEvaluateConvertsNumericStringsBeforeIntegerEvaluation(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $text = Domain::string(10, Collation::binary());
        self::assertSame(2, (new ParameterRead('1.5', $text, Domain::integer(), true))->evaluate($frame));
        self::assertSame(10, (new ParameterRead('1e1', $text, Domain::integer(), true))->evaluate($frame));
        self::assertSame('1', (new ParameterRead(1, Domain::integer(), Domain::decimal(65, 30), true))->evaluate($frame));
        self::assertSame([], $session->diagnostics->conditions);
        self::assertNull((new ParameterRead(null, Domain::null(), Domain::integer(), true))->evaluate($frame));
    }
}
