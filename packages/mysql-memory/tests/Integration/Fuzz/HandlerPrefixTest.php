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
final class HandlerPrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION, SQLWARNING, SQLEXCEPTION SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR SQLEXCEPTION, SQLWARNING, SQLEXCEPTION SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR 1,1 SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR 1,1 SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR SQLSTATE \'45000\',SQLSTATE \'45000\' SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR SQLSTATE \'45000\',SQLSTATE \'45000\' SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR 1,4294967297 SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR 1,4294967297 SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR SQLWARNING,NOT FOUND SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR SQLWARNING,NOT FOUND SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR c,c SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR c,c SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR c,1 SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR c,1 SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR 1,c SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR 1,c SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR c,SQLEXCEPTION,SQLEXCEPTION SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR c,SQLEXCEPTION,SQLEXCEPTION SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION,SQLEXCEPTION,c SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; DECLARE EXIT HANDLER FOR SQLEXCEPTION,SQLEXCEPTION,c SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR 1 BEGIN END; DECLARE EXIT HANDLER FOR 1 SET @x=?; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; BEGIN DECLARE EXIT HANDLER FOR c,1 SET @x=?; END; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 1; BEGIN DECLARE c CONDITION FOR 2; DECLARE EXIT HANDLER FOR c,1 SET @x=?; END; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR 1 BEGIN END; BEGIN DECLARE EXIT HANDLER FOR 1 SET @x=?; END; END',
            'CREATE PROCEDURE d.p() BEGIN DECLARE EXIT HANDLER FOR 1 BEGIN DECLARE EXIT HANDLER FOR 2 BEGIN END; END; DECLARE EXIT HANDLER FOR 2 SET @x=?; END',
        ];
        $statements[] = (string) file_get_contents(__DIR__ . '/fixtures/handler-prefix.sql');
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Handler identities, lexical scopes and later malformed operands
     */
    #[DataProvider('providerReleases')]
    public function testHandlerConditionsResolveBeforeTheirBodyIsRead(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared)), $statements);

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
