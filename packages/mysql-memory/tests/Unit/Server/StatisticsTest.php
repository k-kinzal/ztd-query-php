<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use ArrayObject;
use MySqlMemory\Instance;
use MySqlMemory\Server\Client;
use MySqlMemory\Server\Statistics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Statistics::class)]
#[Small]
final class StatisticsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function providerClocks(): iterable
    {
        yield 'startup' => [1000, '0', '0.000'];
        yield 'one second' => [1001, '1', '101.000'];
        yield 'truncated average' => [1003, '3', '33.666'];
        yield 'past timestamp' => [999, '18446744073709551615', '0.000'];
    }

    #[DataProvider('providerClocks')]
    public function testAnswerReportsCurrentCountersAndThePinnedClock(int $timestamp, string $uptime, string $average): void
    {
        $packets = new ArrayObject();
        $instance = new Instance();
        $instance->started = 1000.0;
        $client = new Client($instance, 7, static function (string $bytes) use ($packets): void {
            $packets->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $session = $client->session();
        $session->variables->session['timestamp'] = $timestamp;
        $session->variables->rowCount = 5;
        $session->diagnostics->warning(1292, 'retained');
        $instance->registry->status->add('Queries', amount: 100);
        $instance->registry->status->add('Flush_commands', amount: 4);

        self::assertTrue((new Statistics($client))->answer());
        self::assertSame(["\x00\x00\x00\x02\x00\x00\x00", "Uptime: {$uptime}  Threads: 1  Questions: 101  Slow queries: 0  Opens: 0  Flush tables: 4  Open tables: 0  Queries per second avg: {$average}"], $packets->getArrayCopy());
        self::assertSame(0, $instance->registry->status->read('Questions', $session->id));
        self::assertSame(5, $session->variables->rowCount);
        self::assertSame([['Warning', 1292, 'retained']], $session->diagnostics->conditions);
    }
}
