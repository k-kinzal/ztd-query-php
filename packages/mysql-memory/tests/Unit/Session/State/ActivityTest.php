<?php

declare(strict_types=1);

namespace Tests\Unit\Session\State;

use MySqlMemory\Instance;
use MySqlMemory\Session\State\Activity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Activity::class)]
#[Small]
final class ActivityTest extends TestCase
{
    public function testBeginAllocatesAcrossSessions(): void
    {
        $instance = new Instance();
        $first = $instance->connect();
        $second = $instance->connect();
        $first->activity->begin();
        $second->activity->begin();

        self::assertSame(1, $first->variables->read('statement_id'));
        self::assertSame(2, $second->variables->read('statement_id'));
    }

    public function testElapsedResetsAtTheNextStatement(): void
    {
        $session = (new Instance())->connect();
        $session->instance->registry->threads->pass(3);
        self::assertContains($session->activity->elapsed(), [3, 4]);
        $session->activity->begin();
        self::assertContains($session->activity->elapsed(), [0, 1]);
    }

    public function testElapsedUsesThePinnedClock(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET timestamp=2100000000');
        $before = (int) floor($session->instance->registry->threads->now());
        $elapsed = $session->activity->elapsed();
        $after = (int) floor($session->instance->registry->threads->now());

        self::assertGreaterThanOrEqual($before - 2100000000, $elapsed);
        self::assertLessThanOrEqual($after - 2100000000, $elapsed);
    }
}
