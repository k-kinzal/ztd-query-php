<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Clock;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Clock as ClockKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Clock::class)]
#[Small]
final class ClockTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheValue(): void
    {
        $domain = new Domain(Kind::Date, Field::Date, 10);

        self::assertSame($domain, (new Clock(ClockKind::CurrentDate, $domain))->domain());
    }

    public function testEvaluateReadsTheUtcInstantTheStatementStartedWithItsFractionalDigits(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 1709210096.123456));

        self::assertSame(
            ['2024-02-29 12:34:56.123456', '2024-02-29 12:34:56.123', '2024-02-29 12:34:56'],
            [
                (new Clock(ClockKind::UtcTimestamp, new Domain(Kind::DateTime, Field::DateTime, 26, 6)))->evaluate($frame),
                (new Clock(ClockKind::UtcTimestamp, new Domain(Kind::DateTime, Field::DateTime, 23, 3)))->evaluate($frame),
                (new Clock(ClockKind::UtcTimestamp, new Domain(Kind::DateTime, Field::DateTime, 19)))->evaluate($frame),
            ],
        );
    }

    public function testEvaluateReadsTheUtcDateAndTimeOfTheStatementStart(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 1709210096.123456));

        self::assertSame(
            ['2024-02-29', '12:34:56.12'],
            [(new Clock(ClockKind::UtcDate, new Domain(Kind::Date, Field::Date, 10)))->evaluate($frame), (new Clock(ClockKind::UtcTime, new Domain(Kind::Time, Field::Time, 11, 2)))->evaluate($frame)],
        );
    }

    public function testEvaluateReadsTheStatementStartInTheZoneOfTheServer(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 1709210096.0));

        self::assertSame(date('Y-m-d H:i:s', 1709210096), (new Clock(ClockKind::Now, new Domain(Kind::DateTime, Field::DateTime, 19)))->evaluate($frame));
    }

    public function testEvaluateReadsTheCurrentInstantForSysdate(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertNotSame(date('Y-m-d', 0), (new Clock(ClockKind::SystemDate, new Domain(Kind::Date, Field::Date, 10)))->evaluate($frame));
    }
}
