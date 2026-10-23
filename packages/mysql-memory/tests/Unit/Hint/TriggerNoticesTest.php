<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use MySqlMemory\Hint\TriggerNotices;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TriggerNotices::class)]
#[Small]
final class TriggerNoticesTest extends TestCase
{
    public function testDuringAppendsTheResourceNoticeAfterAResolutionFailure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(a INT); CREATE TRIGGER d.tr BEFORE INSERT ON d.t FOR EACH ROW SET NEW.a=(SELECT /*+ RESOURCE_GROUP(a) */ 1)');
        $reply = $session->run('SELECT missing FROM d.t');
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame([1054, 3515], array_column($session->diagnostics->conditions, 1));
        self::assertNull($session->preparation->triggerNotices);
        $session->query('SELECT * FROM d.t');
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testDuringConsumesTheImmediatePreparedExecutionNotice(): void
    {
        $session = (new Instance())->connect();
        $session->preparation->replayedTriggerResource = true;
        $notices = new TriggerNotices();
        $notices->during(static fn () => null, $session);
        $notices->during(static fn () => null, $session);
        self::assertSame([3515], array_column($session->diagnostics->conditions, 1));
        self::assertFalse($session->preparation->replayedTriggerResource);
    }

    public function testLoadWaitsForAWritingHandleInMySql91(): void
    {
        $session = (new Instance('9.1.0'))->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(a INT); CREATE TRIGGER d.tr BEFORE INSERT ON d.t FOR EACH ROW SET NEW.a=(SELECT /*+ RESOURCE_GROUP(a) */ 1)');
        $session->diagnostics->clear();
        $notices = new TriggerNotices();
        $notices->load('d', 't', $session);
        self::assertSame([], $session->diagnostics->conditions);
        $notices->load('d', 't', $session, true);
        self::assertSame([3515], array_column($session->diagnostics->conditions, 1));
        $session->diagnostics->clear();
        $notices->load('d', 't', $session, true);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testReportConsumesItsDeferredConditionOnce(): void
    {
        $session = (new Instance())->connect();
        $notices = new TriggerNotices();
        $notices->resource = true;
        $notices->report($session);
        $notices->report($session);
        self::assertSame([3515], array_column($session->diagnostics->conditions, 1));
        self::assertFalse($notices->resource);
    }
}
