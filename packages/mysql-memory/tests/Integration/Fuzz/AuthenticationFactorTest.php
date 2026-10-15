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
final class AuthenticationFactorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'ALTER USER u 0 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 0 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 0 FACTOR UNREGISTER',
            'ALTER USER u 0 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 1 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 1 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 1 FACTOR UNREGISTER',
            'ALTER USER u 1 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 2 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 2 FACTOR UNREGISTER',
            'ALTER USER u 2 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 3 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 3 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 3 FACTOR UNREGISTER',
            'ALTER USER u 3 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 4 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 4 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 4 FACTOR UNREGISTER',
            'ALTER USER u 4 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 02 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 02 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 02 FACTOR UNREGISTER',
            'ALTER USER u 02 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 0003 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 0003 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 0003 FACTOR UNREGISTER',
            'ALTER USER u 0003 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 2147483647 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 2147483647 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 2147483647 FACTOR UNREGISTER',
            'ALTER USER u 2147483647 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 2147483648 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 2147483648 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 2147483648 FACTOR UNREGISTER',
            'ALTER USER u 2147483648 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 0x2 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 0x2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 0x2 FACTOR UNREGISTER',
            'ALTER USER u 0x2 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER u 2.0 FACTOR INITIATE REGISTRATION',
            'ALTER USER u 2.0 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS \'x\'',
            'ALTER USER u 2.0 FACTOR UNREGISTER',
            'ALTER USER u 2.0 FACTOR INITIATE REGISTRATION BROKEN',
            'ALTER USER \'u\'@\'bad@host\' 1 FACTOR INITIATE REGISTRATION',
            'ALTER USER \'uuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuu\'@\'dns1\' 1 FACTOR INITIATE REGISTRATION',
            'ALTER USER \'u\'@\'dns1\' 1 FACTOR INITIATE REGISTRATION',
            'ALTER USER u ADD 2 FACTOR IDENTIFIED WITH unknown_plugin ADD 2 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u ADD 3 FACTOR IDENTIFIED WITH unknown_plugin ADD 2 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u ADD 2 FACTOR IDENTIFIED WITH unknown_plugin ADD 3 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u ADD 1 FACTOR IDENTIFIED WITH unknown_plugin ADD 2 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u ADD 2 FACTOR IDENTIFIED WITH unknown_plugin ADD 1 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u ADD 1 FACTOR IDENTIFIED WITH unknown_plugin ADD 1 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u MODIFY 2 FACTOR IDENTIFIED WITH unknown_plugin MODIFY 2 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u MODIFY 3 FACTOR IDENTIFIED WITH unknown_plugin MODIFY 2 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u MODIFY 2 FACTOR IDENTIFIED WITH unknown_plugin MODIFY 3 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u MODIFY 1 FACTOR IDENTIFIED WITH unknown_plugin MODIFY 2 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u MODIFY 2 FACTOR IDENTIFIED WITH unknown_plugin MODIFY 1 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u MODIFY 1 FACTOR IDENTIFIED WITH unknown_plugin MODIFY 1 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u DROP 2 FACTOR DROP 2 FACTOR',
            'ALTER USER u DROP 3 FACTOR DROP 2 FACTOR',
            'ALTER USER u DROP 2 FACTOR DROP 3 FACTOR',
            'ALTER USER u DROP 1 FACTOR DROP 2 FACTOR',
            'ALTER USER u DROP 2 FACTOR DROP 1 FACTOR',
            'ALTER USER u DROP 1 FACTOR DROP 1 FACTOR',
            'ALTER USER u ADD 2 FACTOR IDENTIFIED WITH x ADD 2 FACTOR IDENTIFIED WITH y BROKEN',
            'ALTER USER u DROP 2 FACTOR DROP 2 FACTOR, \'v\'@\'bad@host\' DROP 1 FACTOR',
            'ALTER USER u DROP 1 FACTOR DROP 1 FACTOR',
            'CREATE USER u IDENTIFIED BY \'password\'',
            'ALTER USER u DROP 2 FACTOR',
            'ALTER USER u 2 FACTOR INITIATE REGISTRATION',
            'ALTER USER u ADD 2 FACTOR IDENTIFIED WITH unknown_plugin',
            'ALTER USER u 3 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS X\'41\'',
            'ALTER USER u 3 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS _utf8mb4\'x\'',
            'CREATE PROCEDURE p() ALTER USER u 0 FACTOR UNREGISTER',
            'CREATE PROCEDURE d.p() ALTER USER u 0 FACTOR UNREGISTER',
            'CREATE PROCEDURE d.p() BEGIN DECLARE a INT; DECLARE a INT; ALTER USER u 0 FACTOR UNREGISTER; END',
            'CREATE PROCEDURE d.p() BEGIN SELECT DATE \'bad\'; ALTER USER u 0 FACTOR UNREGISTER; END',
            'CREATE PROCEDURE d.p() BEGIN ALTER USER u 0 FACTOR UNREGISTER; SELECT DATE \'bad\'; END',
            'CREATE PROCEDURE d.p() BEGIN SELECT CAST(1 AT LOCAL AS DATE); ALTER USER u 0 FACTOR UNREGISTER; END',
            'CREATE PROCEDURE d.p() BEGIN ALTER USER u 0 FACTOR UNREGISTER; SIGNAL SQLSTATE \'00000\'; END',
            'CREATE PROCEDURE d.p() BEGIN SIGNAL SQLSTATE \'00000\'; ALTER USER u 0 FACTOR UNREGISTER; END',
            'CREATE FUNCTION d.f() RETURNS INT BEGIN ALTER USER u DROP 2 FACTOR DROP 2 FACTOR; RETURN 1; END',
            'SHOW PARSE_TREE ALTER USER u 0 FACTOR UNREGISTER',
            'ALTER USER \'u\'@\'bad@host\' 2 FACTOR UNREGISTER BROKEN',
            'ALTER USER u DROP 2 FACTOR DROP 2 FACTOR, \'vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv\' DROP 3 FACTOR',
            'CREATE PROCEDURE d.p() BEGIN SELECT @@missing; ALTER USER u DROP 2 FACTOR DROP 2 FACTOR; END',
            'CREATE PROCEDURE d.p() BEGIN ALTER USER u DROP 2 FACTOR DROP 2 FACTOR; SELECT @@missing; END',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The operand completion boundaries to compare
     */
    #[DataProvider('providerReleases')]
    public function testFactorsRetainTheirDomainAndInputDiagnosticOrder(string $release, array $statements): void
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
