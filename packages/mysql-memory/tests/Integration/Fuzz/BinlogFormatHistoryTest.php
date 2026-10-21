<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class BinlogFormatHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    /**
     * @throws JsonException When the retained native fixture is malformed
     */
    #[DataProvider('providerReleases')]
    public function testTheSameAnnotationDependsOnTheConnectionsRetainedFormat(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = (new Instance($release))->connect();
            $fixture = file_get_contents(__DIR__ . '/fixtures/binlog-replay.json');
            self::assertIsString($fixture);
            $events = json_decode($fixture, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($events);
            self::assertIsString($events[0]);
            $format = new BinlogEvent(new Text(substr($events[0], 8, -1)));
            $annotation = new BinlogEvent(new Text(base64_encode(pack('VCVVVv', 0, 29, 1, 27, 0, 0) . "\x03abc\0\0\0\0")));
            $history = [$annotation, $format, $annotation, $format, $annotation];
            $sql = array_map(static fn (BinlogEvent $node): string => "BINLOG '" . $node->events->value . "'", $history);
            $expected = array_map(static fn (string $statement): string => serialize((new Native())->observe($native, $statement)), $sql);
            $actual = array_map(static fn (BinlogEvent $node): string => serialize((new Memory())->observe($memory, $node)), $history);

            self::assertSame($expected, $actual, $release);
            self::assertNotNull($memory->binlog->format);
            $fresh = (new Instance($release))->connect();
            $refused = (new Memory())->observe($fresh, $annotation);
            self::assertArrayHasKey('error', $refused);
            self::assertSame(1609, ($refused['error'] ?? [])[0] ?? null);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
