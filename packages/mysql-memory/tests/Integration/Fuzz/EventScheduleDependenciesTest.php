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
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class EventScheduleDependenciesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws RuntimeException When the retained fuzz input cannot be read
     */
    public static function providerReleases(): iterable
    {
        $retained = file_get_contents(__DIR__ . '/fixtures/event-schedule-dependencies.sql');
        if ($retained === false) {
            throw new RuntimeException('The retained event input is missing.');
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            $expressions = [
                '(1,2)', 'ABS((1,2))', '((1,2) + (SELECT 1 FROM absent))',
                '((SELECT 1 FROM absent) + (1,2))', 'CONCAT((1,2),(SELECT 1 FROM absent))',
                '(SELECT 1,2 FROM absent)', '(SELECT 1 UNION SELECT 1,2 FROM absent)',
                'ABS(1,2) + (SELECT 1 FROM absent)', '(SELECT SQL_BUFFER_RESULT 1 FROM absent)',
            ];
            if (!str_starts_with($release, '5.')) {
                $expressions[] = "(WITH c AS (SELECT 1 FROM absent) SELECT '2030-01-01')";
                $expressions[] = '((1,2) + (WITH c AS (SELECT 1 FROM absent) SELECT 1))';
                $expressions[] = '((1,2) + (WITH c AS (SELECT 1) SELECT * FROM c))';
            }
            $inputs = [];
            foreach ($expressions as $expression) {
                $inputs[] = 'ALTER EVENT e ON SCHEDULE AT ' . $expression;
                $inputs[] = 'CREATE EVENT e ON SCHEDULE AT ' . $expression . ' DO SELECT 1';
            }
            foreach (['(1,2)', 'ROW(1,2)', 'ABS((1,2))'] as $operand) {
                $inputs[] = 'ALTER EVENT e ON SCHEDULE EVERY ' . $operand . ' DAY';
                $inputs[] = 'ALTER EVENT e ON SCHEDULE EVERY 1 DAY STARTS ' . $operand;
                $inputs[] = 'ALTER EVENT e ON SCHEDULE EVERY 1 DAY ENDS ' . $operand;
            }
            $inputs[] = 'ALTER EVENT e ON SCHEDULE EVERY (1,2) DAY STARTS (SELECT 1 FROM absent)';
            $inputs[] = 'ALTER EVENT e ON SCHEDULE EVERY (SELECT 1 FROM absent) DAY STARTS (1,2)';
            $statements = [...$inputs, 'CREATE DATABASE event_probe', 'USE event_probe', ...$inputs,
                'ALTER EVENT e ON SCHEDULE AT schedule_time()',
                "CREATE FUNCTION schedule_time() RETURNS DATETIME DETERMINISTIC RETURN '2030-01-01'",
                'ALTER EVENT e ON SCHEDULE AT schedule_time()',
                'CREATE EVENT function_event ON SCHEDULE AT schedule_time() DO SELECT 1',
            ];
            if ($release === '8.4.7') {
                $statements[] = $retained;
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testScheduleDependenciesPrecedeScalarWidthsAfterTheEventHeader(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, 0)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, 0)), $statements);

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
