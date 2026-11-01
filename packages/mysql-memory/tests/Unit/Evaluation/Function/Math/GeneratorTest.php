<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Math\Generator;
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

#[CoversClass(Generator::class)]
#[Small]
final class GeneratorTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheSeed(): void
    {
        $domain = Domain::of(Resolved::integer(), false);

        self::assertSame($domain, (new Generator(new Constant($domain, 1), true))->domain());
    }

    public function testEvaluateReadsTheSeed(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame(7, (new Generator(new Constant(Domain::of(Resolved::integer(), false), 7), true))->evaluate($frame));
    }

    public function testNextAdvancesTheSequenceOfAConstantSeed(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $constant = new Generator(new Constant(Domain::of(Resolved::integer(), false), 1), true);
        $varying = new Generator(new Constant(Domain::of(Resolved::integer(), false), 1), false);

        self::assertSame([0.40540353712197724, 0.8716141803857071, 0.1418603212962489], [$constant->next($frame), $constant->next($frame), $constant->next($frame)]);
        self::assertSame([0.40540353712197724, 0.40540353712197724], [$varying->next($frame), $varying->next($frame)]);
    }

    public function testStartTakesTheLowThirtyTwoBitsOfTheSeed(): void
    {
        $generator = new Generator(new Constant(Domain::of(Resolved::integer(), false), 0), true);

        self::assertSame($generator->start(-1), $generator->start(4294967295));
        self::assertSame($generator->start(1), $generator->start(4294967297));
        self::assertSame([55555555, 0], $generator->start(0));
    }
}
