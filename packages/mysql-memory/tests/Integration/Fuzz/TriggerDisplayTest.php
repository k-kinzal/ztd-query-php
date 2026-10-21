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
final class TriggerDisplayTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, int}>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $release) {
            $statements = [['CREATE DATABASE d', 0], ['USE d', 0], ['CREATE TABLE t(a INT)', 0], ['SET timestamp=1700000000', 0]];
            foreach (['create trigger d.x before insert on d.t for each row begin end', 'create definer=current_user trigger `d`.`x` before insert on `d`.`t` for each row begin end', 'create /* prefix */ trigger x before insert on t for each row begin end;', 'create trigger x before insert on t for each row begin /* body */ end'] as $sql) {
                array_push($statements, [$sql, 0], ['SHOW CREATE TRIGGER x', 0], ['DROP TRIGGER x', 0]);
            }
            if ($release !== '5.6.51') {
                array_push($statements, ['create trigger y before insert on t for each row begin end', 0], ['create trigger z before insert on t for each row follows y begin end', 0], ['SHOW CREATE TRIGGER z', 0]);
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<array{string, int}> $statements
     */
    #[DataProvider('providerStatements')]
    public function testStoredTriggerDisplayRetainsItsReleaseSpecificHeader(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (array $input): string => serialize((new Native())->observe($native, ...$input)), $statements);
                $actual = array_map(static fn (array $input): string => serialize((new Native())->observe($memory, ...$input)), $statements);
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
