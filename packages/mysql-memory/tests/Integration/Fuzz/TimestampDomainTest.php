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
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TimestampDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<Statement>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_TIMESTAMP_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new StaticPortStrategy($port));
            }
            $texts = ['2024-01-02 03:04:05', '20240102030405', '240102030405', '2401020', '240102030', '20240102T030405', '000000000000', '2024-00-02 03:04:05', '2024-02-31 03:04:05', '2024-12-31 23:59:59.9999995', '2024-01-02 03:04:05.1234567+01:00', '2024-01-02 03:04:05.9999999+01:00', '0000-01-01 00:00:00+14:00', '9999-12-31 23:59:59-14:00', '2024-02-31 03:04:05+01:00'];
            $nodes = array_map(static fn (string $text): QueryRoot => new QueryRoot(new Select([], [new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp, $text), new Name('v'))])), $texts);
            yield $release => [$release, $definition, $nodes];
        }
    }

    /**
     * @param list<Statement> $nodes The same timestamp operands across SQL mode and time zone transitions
     */
    #[DataProvider('providerStructures')]
    public function testSameTimestampStructuresFollowCalendarModesAndTimeZone(string $release, DisposableMySql $definition, array $nodes): void
    {
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $fixture = ['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci', "SET time_zone = '+00:00'", 'SET timestamp = 1700000000.129999', "SET sql_mode = ''"];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $stages = [[], ["SET sql_mode = 'ALLOW_INVALID_DATES'"], ["SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'"], ["SET sql_mode = 'ALLOW_INVALID_DATES'", "SET time_zone = '+09:00'"]];
                $results = array_map(static function (array $setup) use ($native, $session, $nodes): array {
                    array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $setup);
                    array_map($session->run(...), $setup);
                    $profile = $session->semantics()->profile();
                    $expected = array_map(static fn (Statement $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $profile))), $nodes);
                    $session->text = 'unrelated invalid input';
                    $actual = array_map(static fn (Statement $node): string => serialize((new Memory())->observe($session, $node)), $nodes);

                    return [$expected, $actual];
                }, $stages);

                self::assertSame(array_column($results, 0), array_column($results, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
