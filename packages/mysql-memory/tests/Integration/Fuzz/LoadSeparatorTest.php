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
final class LoadSeparatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [];
        foreach (["'<row>'", "'<猫>'", "'é'", "'a\\0猫'"] as $tag) {
            $statements[] = "LOAD XML INFILE 'text' INTO TABLE t ROWS IDENTIFIED BY " . $tag;
            $statements[] = "LOAD XML INFILE 'text' INTO TABLE missing.t ROWS IDENTIFIED BY " . $tag;
            $statements[] = "LOAD XML LOCAL INFILE 'text' INTO TABLE missing.t ROWS IDENTIFIED BY " . $tag;
        }
        foreach (["FIELDS TERMINATED BY X'80'", "FIELDS TERMINATED BY X'80' ENCLOSED BY 'xx'", "FIELDS TERMINATED BY X'80' TERMINATED BY 'a'", "FIELDS ESCAPED BY X'80'", "LINES STARTING BY X'80'", "LINES TERMINATED BY X'80'", "LINES STARTING BY X'80' STARTING BY 'a'"] as $options) {
            $statements[] = "LOAD DATA INFILE 'text' INTO TABLE t " . $options;
            $statements[] = "LOAD DATA INFILE 'text' INTO TABLE missing.t " . $options;
        }
        array_push(
            $statements,
            'CREATE DATABASE d',
            'USE d',
            "LOAD DATA INFILE 'text' INTO TABLE t FIELDS TERMINATED BY X'80'",
            "LOAD DATA LOCAL INFILE 'text' INTO TABLE t FIELDS TERMINATED BY X'80'",
            "LOAD XML INFILE 'text' INTO TABLE t FIELDS TERMINATED BY X'80'",
            "LOAD DATA INFILE 'text' INTO TABLE t CHARACTER SET missing FIELDS TERMINATED BY X'80'",
            "LOAD DATA INFILE 'text' INTO TABLE t FIELDS ENCLOSED BY 'xx' OPTIONALLY ENCLOSED BY 'a'",
            'CREATE TABLE t(a INT)',
            "LOAD DATA INFILE 'text' INTO TABLE t FIELDS TERMINATED BY X'80'"
        );
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $inputs = $statements;
            if (!str_starts_with($release, '5.')) {
                $inputs[] = "LOAD DATA CONCURRENT INFILE 'a''b' INTO TABLE HELP ._sqlfaker_identifier PARTITION ( PERSIST ) COLUMNS TERMINATED BY 0xb14def4daefd8CEe2dCafDFF2FFeAcA5 IGNORE 0000656865479 ROWS ( ) MEMORY = 1K";
            }
            yield $release => [$release, $inputs];
        }
    }

    /**
     * @param list<string> $statements Separator overrides and competing target conditions
     */
    #[DataProvider('providerReleases')]
    public function testEffectiveSeparatorWarningsPrecedeTargetLookup(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
