<?php

declare(strict_types=1);

namespace Tests\Unit\Server;

use ArrayObject;
use MySqlMemory\Instance;
use MySqlMemory\Protocol\PayloadReader;
use MySqlMemory\Server\Client;
use MySqlMemory\Server\Refresh;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Refresh::class)]
#[Small]
final class RefreshTest extends TestCase
{
    public function testAnswerSendsTheDeprecationCountInAnOkPacket(): void
    {
        $packets = new ArrayObject();
        $client = new Client(new Instance('8.0.44'), 7, static function (string $bytes) use ($packets): void {
            $packets->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        (new Refresh($client))->answer(new PayloadReader("\0"));

        self::assertSame(["\x00\x00\x00\x02\x00\x00\x00", "\x00\x00\x00\x02\x00\x01\x00"], $packets->getArrayCopy());
    }

    public function testAnswerCommitsAndRecordsOneRefreshRequest(): void
    {
        $client = new Client(new Instance('8.0.44'), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $session = $client->session();
        $session->query('START TRANSACTION');
        $questions = $client->instance->registry->status->read('Questions', $session->id);

        self::assertTrue((new Refresh($client))->answer(new PayloadReader("\0")));
        self::assertFalse($session->transaction->active());
        self::assertSame($questions + 1, $client->instance->registry->status->read('Questions', $session->id));
        self::assertSame(1, $client->instance->registry->status->read('Com_flush', $session->id));
        self::assertSame([['Warning', 1287, "'COM_REFRESH' is deprecated and will be removed in a future release. Please use FLUSH statement instead"]], $session->diagnostics->conditions);
    }

    public function testAnswerRecordsTheRemovedCommandWithoutCommitting(): void
    {
        $client = new Client(new Instance('9.1.0'), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $session = $client->session();
        $session->query('START TRANSACTION');

        self::assertTrue((new Refresh($client))->answer(new PayloadReader("\x10")));
        self::assertTrue($session->transaction->active());
        self::assertSame([['Error', 1047, 'Unknown command']], $session->diagnostics->conditions);
        self::assertSame(0, $client->instance->registry->status->read('Com_flush', $session->id));
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function providerRefreshDiagnostics(): iterable
    {
        foreach (['5.6.51' => [], '8.0.44' => [1287], '9.1.0' => [1047]] as $version => $added) {
            yield $version => [$version, $added];
        }
    }

    /**
     * @param list<int> $added
     */
    #[DataProvider('providerRefreshDiagnostics')]
    public function testAnswerAppendsToConditionsRetainedByShowWarnings(string $version, array $added): void
    {
        $client = new Client(new Instance($version), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $session = $client->session();
        $session->query("SELECT 'a'+1,'b'+1");
        $session->query('SHOW WARNINGS');
        (new Refresh($client))->answer(new PayloadReader("\0"));

        self::assertSame([1292, 1292, ...$added], array_column($session->diagnostics->conditions, 1));
    }

    public function testApplyCombinesCacheLogAndStatusFlags(): void
    {
        $client = new Client(new Instance('8.0.44'), 7, static function (string $bytes): void {
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $registry = $client->instance->registry;
        $client->instance->dictionary->cache->open('d', 't');
        $registry->status->add('Com_do', $client->id);
        (new Refresh($client))->apply(2 | 4 | 16);

        self::assertSame([1, 2], $registry->binaryLog->files);
        self::assertSame(1, $registry->status->read('Flush_commands'));
        self::assertSame(0, $registry->status->read('Com_do', $client->id));
        (new Refresh($client))->apply(128 | 64);
        self::assertSame([1], $registry->binaryLog->files);
    }

}
