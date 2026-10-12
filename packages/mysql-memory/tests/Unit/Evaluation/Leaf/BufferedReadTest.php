<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\BufferedRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(BufferedRead::class)]
#[Small]
final class BufferedReadTest extends TestCase
{
    public function testDomainRetainsTheStoredColumnDomain(): void
    {
        $domain = Domain::integer()->withNullable(true);

        self::assertSame($domain, (new BufferedRead($domain, 0))->domain());
    }

    public function testInitialDistinguishesAnUnreadNullableFieldFromANonnullableField(): void
    {
        self::assertNull((new BufferedRead(Domain::integer()->withNullable(true), 0))->initial());
        self::assertSame('0.00', (new BufferedRead(Domain::decimal(5, 2), 0))->initial());
    }

    public function testEvaluateCanRetainAnInitialMarkerDespiteDiscardedRows(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $frame->buffered = [12];
        $nullable = Domain::integer()->withNullable(true);

        self::assertSame(0, (new BufferedRead($nullable, 0, false, true))->evaluate($frame));
        self::assertNull((new BufferedRead(Domain::integer(), 0, true, true))->evaluate($frame));
        self::assertSame(12, (new BufferedRead($nullable, 0, false))->evaluate($frame));
    }

    public function testEvaluateKeepsBufferedNullAndIgnoresTheInputRow(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [99]);
        $value = new BufferedRead(Domain::integer(), 0);
        $initial = $value->evaluate($frame);
        $frame->buffered = [12];
        $stored = $value->evaluate($frame);
        $frame->buffered = [null];

        self::assertSame([0, 12, null], [$initial, $stored, $value->evaluate($frame)]);
    }
}
