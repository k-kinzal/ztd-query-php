<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ProfileMetadataTest extends TestCase
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

    #[DataProvider('providerReleases')]
    public function testProfileSectionsRetainTheirVersionedColumnMetadata(string $release): void
    {
        $statements = [
            'SHOW PROFILES', 'SHOW PROFILE', 'SHOW PROFILE CPU',
            'SHOW PROFILE BLOCK IO', 'SHOW PROFILE CONTEXT SWITCHES',
            'SHOW PROFILE PAGE FAULTS', 'SHOW PROFILE IPC', 'SHOW PROFILE SWAPS',
            'SHOW PROFILE SOURCE', 'SHOW PROFILE ALL',
            'SHOW PROFILE LIMIT 0', 'SHOW PROFILE LIMIT 0 OFFSET 0', 'SHOW PROFILE LIMIT 0 OFFSET 1',
            'SHOW PROFILE LIMIT 1,0', 'SHOW PROFILE CPU LIMIT 000', 'SHOW PROFILE ALL FOR QUERY 0 LIMIT 0',
            'SHOW PROFILE LIMIT ?',
            "PREPARE s FROM 'SHOW PROFILE LIMIT ?'", 'SET @limit=0', 'EXECUTE s USING @limit',
            'DEALLOCATE PREPARE s', "PREPARE s FROM 'SHOW PROFILES'", 'EXECUTE s',
            'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT)', 'EXECUTE s',
            'CREATE PROCEDURE p() SHOW PROFILE', 'CREATE PROCEDURE q() SHOW PROFILES',
            'SHOW PROFILE LIMIT missing',
            "PREPARE bad FROM 'SHOW PROFILE LIMIT missing'",

        ];
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, $sql === 'SHOW PROFILE LIMIT ?' ? 1 : 0)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, $sql === 'SHOW PROFILE LIMIT ?' ? 1 : 0)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
    #[DataProvider('providerReleases')]
    public function testPreparedDirectStructureKeepsOnlyExecutionConditions(string $release): void
    {
        $node = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile(limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter('?')));
        $observation = (new \Fuzz\Structure\Audit\Comparison())->compare($node, new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::from('mysql-' . $release)));

        self::assertTrue($observation['matches'], serialize($observation));
        self::assertSame([], $observation['native']['warnings']);
    }

}
