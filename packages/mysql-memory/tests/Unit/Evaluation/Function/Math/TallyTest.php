<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Math\Tally;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as Resolved;

#[CoversClass(Tally::class)]
#[Small]
final class TallyTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheArgument(): void
    {
        $domain = Domain::of(Resolved::integer(), false);

        self::assertSame($domain, (new Tally(new Constant($domain, 1)))->domain());
    }

    public function testEvaluateReadsTheArgumentAndCountsTheRow(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $tally = new Tally(new Constant(Domain::of(Resolved::integer(), false), 3));

        self::assertSame([3, 3], [$tally->evaluate($frame), $tally->evaluate($frame)]);
        self::assertSame(2, $tally->row($frame));
    }

    public function testRowIsOneBeforeAnyRow(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame(1, (new Tally(new Constant(Domain::of(Resolved::integer(), false), 3)))->row($frame));
    }
}
