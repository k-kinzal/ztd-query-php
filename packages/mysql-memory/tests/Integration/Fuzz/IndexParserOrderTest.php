<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class IndexParserOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws RuntimeException When the retained fuzz input is missing
     */
    public static function providerInputs(): iterable
    {
        $retained = file_get_contents(__DIR__ . '/fixtures/index-parser-prefix.sql');
        if ($retained === false) {
            throw new RuntimeException('The retained index parser input is missing.');
        }
        $statements = ['SET NAMES utf8mb4', $retained, 'CREATE DATABASE d', 'CREATE TABLE d.t(a TEXT)'];
        foreach (['ALGORITHM=bad', 'LOCK=bad'] as $option) {
            array_push(
                $statements,
                'CREATE FULLTEXT INDEX i ON t(a DESC) WITH PARSER zz ' . $option,
                'CREATE FULLTEXT INDEX i ON d.t(a(0)) WITH PARSER zz ' . $option,
                'CREATE FULLTEXT INDEX i ON d.t(a(0),a(0)) WITH PARSER zz WITH PARSER ngram ' . $option,
                'CREATE FULLTEXT INDEX i ON d.t(a(0)) WITH PARSER ngram WITH PARSER zz ' . $option,
                'CREATE FULLTEXT INDEX i ON d.t(a(0)) WITH PARSER ngram ' . $option,
                'ALTER TABLE d.t ADD FULLTEXT(a(0)) WITH PARSER zz, ' . $option,
                'CREATE PROCEDURE d.p() CREATE FULLTEXT INDEX i ON d.t(a(0)) WITH PARSER zz ' . $option,
                'CREATE FULLTEXT INDEX i ON d.t(a DESC) WITH PARSER zz ' . $option,
                'ALTER TABLE d.t ADD FULLTEXT(a) WITH PARSER zz, ' . $option,
                'ALTER TABLE d.t ' . $option . ', ADD FULLTEXT(a) WITH PARSER zz',
                'CREATE PROCEDURE d.p() CREATE FULLTEXT INDEX i ON d.t(a) WITH PARSER zz ' . $option,
                'CREATE FULLTEXT INDEX i ON d.t(a) WITH PARSER zz ' . $option . ' TRAILING'
            );
        }
        array_push($statements, 'USE d', $retained);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Completed full-text parser dependencies and later invalid modifiers
     */
    #[DataProvider('providerInputs')]
    public function testParserDependenciesPrecedeLaterRefusals(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
