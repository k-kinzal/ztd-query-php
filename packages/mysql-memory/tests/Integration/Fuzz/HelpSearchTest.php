<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use Fuzz\Target\Servers;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class HelpSearchTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function providerSearches(): iterable
    {
        $fixture = [
            'DELETE FROM mysql.help_relation', 'DELETE FROM mysql.help_keyword', 'DELETE FROM mysql.help_topic', 'DELETE FROM mysql.help_category',
            "INSERT INTO mysql.help_category VALUES (1,'Root',NULL,''),(2,'Child',1,''),(3,'Sibling',1,''),(4,'Leaf',2,'')",
            "INSERT INTO mysql.help_topic VALUES (1,'Alpha',1,'Alpha description','Alpha example','https://example.test/alpha'),(2,'Beta',2,'Beta description','',''),(3,'Child',3,'Child topic','',''),(4,'Alpine',2,'Alpine description','',''),(5,'àlgebra',1,'Accent','',''),(6,'Zed',2,'Upper','',''),(7,'zedlow',2,'Lower','','')",
            "INSERT INTO mysql.help_keyword VALUES (1,'Related'),(2,'Singular'),(3,'AKeyword'),(4,'Alpha'),(5,'KOne'),(6,'KTwo'),(7,'Unused'),(8,'Root')",
            'INSERT INTO mysql.help_relation VALUES (1,1),(2,1),(2,2),(2,3),(3,4),(4,5),(2,6),(4,6),(1,8)',
        ];
        $searches = ['none', 'Alpha', 'ALPHA', 'a%', 'Root', 'Child', 'S%', 'Related', 'Singular', 'AKeyword', '%', '_', 'R%', 'Leaf', 'Al\\%', 'Alpha ', ' alpha', 'K%', 'KOne', 'KTwo', 'Unused', 'Algebra', 'álgebra', 'Z%', 'r%', '%e%', '%root%', '%%', '%i%', '%a%'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . ':custom' => [$release, $fixture, $searches];
            yield $release . ':installed' => [$release, [], ['SHUTDOWN', 'RESTART', 'SELECT', 'contents', 'Functions', 'abs', 'SELECT%', 'zz-no-help-topic', 'show tables', 'data types', 'JOIN', 'C%']];
        }
    }

    /**
     * @param list<string> $fixture The independently authored help table data, or an installed catalog
     * @param list<string> $patterns The exact topics, keyword collisions and category patterns
     */
    #[DataProvider('providerSearches')]
    public function testHelpMatchesNativeDataAndMetadata(string $release, array $fixture, array $patterns): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port . ';charset=utf8mb4', $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $native->exec('SET NAMES utf8mb4');
            array_map(static fn (string $sql): int|false => $native->exec($sql), $fixture);
            $server = Server::start($release, help: (new Servers())->help($native));
            try {
                $memory = new PDO($server->dsn() . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $memory->exec('SET NAMES utf8mb4');
                $observer = new Observer();
                $statements = array_map(static fn (string $pattern): string => 'HELP ' . $native->quote($pattern), $patterns);
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
