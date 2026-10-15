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
final class EvaluationDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $inputs = ['DO *', 'DO t.*', 'DO do_domain.t.*', 'DO *, missing()', 'DO missing(), t.*', 'DO SUM(1), t.*', 'DO t.*, SUM(1)', 'DO *, !1', 'DO t.*, u.*', 'DO *, missing(), !1', 'DO *, !1, missing()'];
        $programs = ['CREATE PROCEDURE do_domain.p() DO *', 'CALL do_domain.p()', 'CREATE PROCEDURE do_domain.q() DO t.*', 'CALL do_domain.q()'];
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, [...$inputs, 'CREATE DATABASE do_domain', ...$programs, 'USE do_domain', ...$inputs, 'CREATE TABLE t(a INT)', ...$inputs, 'CREATE PROCEDURE h() BEGIN DECLARE CONTINUE HANDLER FOR 1096 SET @handled=1; DO *; END', 'CALL h()', 'SELECT @handled']];
        }
    }

    /**
     * @param list<string> $statements Wildcard refusals before and after schema selection, including stored procedure definitions
     */
    #[DataProvider('providerStatements')]
    public function testEvaluationRefusalsAndDeferredBodiesMatchNative(string $release, array $statements): void
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
