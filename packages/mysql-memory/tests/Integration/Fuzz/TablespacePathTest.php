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
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TablespacePathTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $files = ['', 'a', 'a.ibd', '.ibd', 'ab.ibd', 'a/b', 'a\\b', 'ab/cd', 'ab\\cd', 'a/b.ibd', 'a\\b.ibd', './a', './a.ibd', '../a', '../a.ibd', '/a', '/a.ibd', 'a/', 'a\\', 'a/.ibd', 'a\\.ibd', 'C:\\a', 'C:\\a.ibd', "a\nb", "a\0b", 'a.IBD', 'a.ibd ', 'abcd', 'abcde', 'abc.def', 'a:b.ibd', 'C:a.ibd', 'abc:d.ibd', 'foo/../b.ibd', '././c.ibd', 'foo/../../d.ibd', 'e.ibd ', 'e.ibd', "f.ibd\t", "g.ibd\n", "h.ibd\0stuff", 'h.ibd', 'b:bad/name.ibd', './x:y.ibd'];
        $statements = [];
        foreach ($files as $index => $file) {
            $quoted = str_replace(['\\', "'", "\0"], ['\\\\', "''", '\\0'], $file);
            $statements[] = 'CREATE TABLESPACE probe_' . $index . " ADD DATAFILE '" . $quoted . "'";
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Datafile paths with ordered diagnostics and file identity histories
     */
    #[DataProvider('providerReleases')]
    public function testDatafilePathsAndWarningsMatchMySql(string $release, array $statements): void
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
