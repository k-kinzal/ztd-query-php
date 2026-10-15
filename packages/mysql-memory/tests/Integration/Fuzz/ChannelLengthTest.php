<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ChannelLengthTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<ChangeReplicationSource>, list<?int>, list<string>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $terminology = str_starts_with($release, '5.') ? Terminology::Legacy : Terminology::Current;
            $nodes = [];
            $errors = [];
            foreach (['a', 'é', '猫', '😀'] as $character) {
                foreach ([64, 65] as $length) {
                    $nodes[] = new ChangeReplicationSource($terminology, [new SourceOption($terminology, SourceOptionKind::LogPosition, new Numeral('4'))], new Text(str_repeat($character, $length)));
                    $errors[] = $length === 64 ? null : 3076;
                }
            }
            foreach (["a\0" . str_repeat('x', 80), str_repeat('x', 65) . "\0ignored"] as $position => $name) {
                $nodes[] = new ChangeReplicationSource($terminology, [new SourceOption($terminology, SourceOptionKind::LogPosition, new Numeral('4'))], new Text($name));
                $errors[] = $position === 0 ? null : 3076;
            }
            foreach ([SourceOptionKind::AutoPosition, SourceOptionKind::HeartbeatPeriod] as $kind) {
                $nodes[] = new ChangeReplicationSource($terminology, [new SourceOption($terminology, $kind, $kind === SourceOptionKind::AutoPosition ? new Numeral('1') : new NumberLiteral('0.0001'))], new Text(str_repeat('a', 65)));
                $errors[] = 3076;
            }
            $fixture = ['SET GLOBAL server_id=1'];
            if (str_starts_with($release, '5.')) {
                yield $release . ': FILE' => [$release, $nodes, array_fill(0, count($nodes), 3077), $fixture];
                $fixture[] = "SET GLOBAL master_info_repository='TABLE', relay_log_info_repository='TABLE'";
            }
            yield $release => [$release, $nodes, $errors, $fixture];
        }
    }

    /**
     * @param list<ChangeReplicationSource> $nodes Boundary values retained as original semantic structures
     * @param list<?int> $errors Outcomes after the server is configured for replication
     * @param list<string> $fixture Repository state supplied to both servers
     */
    #[DataProvider('providerStructures')]
    public function testCreationCountsCharactersAndPreservesEarlierHeartbeatNotices(string $release, array $nodes, array $errors, array $fixture): void
    {
        $container = Testcontainers::run((new DisposableMySql($release))->withCommands(['mysqld', '--server-id=1']));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $session = (new Instance($release))->connect();
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $session->text = 'unrelated invalid input';
                $profile = $session->semantics()->profile();
                $reset = str_starts_with($release, '5.') ? 'RESET SLAVE ALL' : 'RESET REPLICA ALL';
                $expected = array_map(static function (ChangeReplicationSource $node) use ($native, $profile, $reset): string {
                    $native->query($reset);

                    return serialize((new Native())->observe($native, (new Reference())->sql($node, $profile)));
                }, $nodes);
                $actual = array_map(static function (ChangeReplicationSource $node) use ($session, $reset): array {
                    $session->run($reset);
                    $session->text = 'unrelated invalid input';
                    $result = (new Memory())->observe($session, $node);

                    return [serialize($result), $result['error'][0] ?? null];
                }, $nodes);

                self::assertSame($expected, array_column($actual, 0));
                self::assertSame($errors, array_column($actual, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
