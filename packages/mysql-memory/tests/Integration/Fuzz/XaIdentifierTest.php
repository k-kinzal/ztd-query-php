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
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class XaIdentifierTest extends TestCase
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
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerInputs(): iterable
    {
        $long = str_repeat('b', 65);
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (["'$long'", "'g','$long'"] as $parts) {
            foreach (['', ',1', ',1.5', ',2e3', ',9223372036854775808', ',0x8000000000000000'] as $format) {
                $xid = $parts . $format;
                $statements[] = 'XA START ' . $xid;
                $statements[] = 'XA START ' . $xid . ' /* gap */ JOIN';
                $statements[] = 'XA START ' . $xid . ' trailing';
            }
        }
        $statements = [...$statements,
            "XA START '$long',", "XA START '$long','unfinished", "XA START 'g','$long',?",
            "XA START 'g','$long',1.5 'unfinished", "XA START 'g','$long',1.5; SELECT 1",
            "CREATE PROCEDURE p() BEGIN IF 0 THEN XA START '$long'; END IF; END",
            "CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; XA START '$long'; END",
            "CREATE PROCEDURE p() BEGIN SELECT DATE 'bad'; XA START '$long'; END",
            "SHOW PARSE_TREE XA START 'g','$long',1.5",
            "XA PREPARE 'g','b',9223372036854775808", "XA COMMIT 'g','b',18446744073709551616",
        ];
        foreach (self::providerReleases() as $name => [$release]) {
            yield $name => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Identifier refusals and competing conditions
     */
    #[DataProvider('providerInputs')]
    public function testInputRefusalRetainsItsNativePositionAndEarlierConditions(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = new mysqli($server->host, 'root', '', port: $server->port);
            try {
                $native->set_charset('utf8mb4');
                $memory->set_charset('utf8mb4');
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $memory->close();
            }
        } finally {
            $server->stop();
            $container->stop();
        }
    }

    #[DataProvider('providerReleases')]
    public function testSameConstructedIdentifierCommitsAfterPreparation(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $node = new XaCommit(new Xid(new Text('g'), new Text('b'), new Numeral('1.5')));
                $sql = (new Reference())->sql($node, $session->semantics()->profile());
                $session->text = 'unrelated invalid input';
                $before = (new Memory())->observe($session, $node);
                self::assertSame((new Native())->observe($native, $sql), $before);
                self::assertSame(1397, $before['error'][0] ?? null);
                $fixture = ["XA START 'g','b',1.5", "XA END 'g','b',1.5", "XA PREPARE 'g','b',1.5"];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $session->text = 'unrelated invalid input';
                $after = (new Memory())->observe($session, $node);

                self::assertSame((new Native())->observe($native, $sql), $after);
                self::assertArrayNotHasKey('error', $after);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
