<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class AccountAuthenticationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerSql(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE USER u IDENTIFIED BY \'old\'', 'CREATE USER v IDENTIFIED BY \'old\'', 'ALTER USER u IDENTIFIED BY PASSWORD \'\'', 'ALTER USER u IDENTIFIED BY PASSWORD \'text\'', 'ALTER USER u IDENTIFIED BY PASSWORD \'*0123456789012345678901234567890123456789\'', 'ALTER USER u IDENTIFIED BY PASSWORD \'text\' REQUIRE SSL', 'ALTER USER u IDENTIFIED BY PASSWORD \'text\', v IDENTIFIED BY \'new\'', 'ALTER USER u IDENTIFIED BY PASSWORD \'text\' GARBAGE', 'ALTER USER u IDENTIFIED BY PASSWORD \'text\' REQUIRE', 'ALTER USER u IDENTIFIED BY PASSWORD \'text\' /* trailing */', 'ALTER USER missing IDENTIFIED BY PASSWORD \'\'', 'ALTER USER missing IDENTIFIED BY PASSWORD \'text\'', 'ALTER USER missing IDENTIFIED BY PASSWORD \'*0123456789012345678901234567890123456789\'', 'ALTER USER missing IDENTIFIED BY PASSWORD \'text\' REQUIRE SSL', 'ALTER USER missing IDENTIFIED BY PASSWORD \'text\', v IDENTIFIED BY \'new\'', 'ALTER USER missing IDENTIFIED BY PASSWORD \'text\' GARBAGE', 'ALTER USER missing IDENTIFIED BY PASSWORD \'text\' REQUIRE', 'ALTER USER missing IDENTIFIED BY PASSWORD \'text\' /* trailing */', 'ALTER USER IF EXISTS missing IDENTIFIED BY PASSWORD \'\'', 'ALTER USER IF EXISTS missing IDENTIFIED BY PASSWORD \'text\'', 'ALTER USER IF EXISTS missing IDENTIFIED BY PASSWORD \'*0123456789012345678901234567890123456789\'', 'ALTER USER IF EXISTS missing IDENTIFIED BY PASSWORD \'text\' REQUIRE SSL', 'ALTER USER IF EXISTS missing IDENTIFIED BY PASSWORD \'text\', v IDENTIFIED BY \'new\'', 'ALTER USER IF EXISTS missing IDENTIFIED BY PASSWORD \'text\' GARBAGE', 'ALTER USER IF EXISTS missing IDENTIFIED BY PASSWORD \'text\' REQUIRE', 'ALTER USER IF EXISTS missing IDENTIFIED BY PASSWORD \'text\' /* trailing */', 'ALTER USER USER() IDENTIFIED BY PASSWORD \'\'', 'ALTER USER USER() IDENTIFIED BY PASSWORD \'text\'', 'ALTER USER USER() IDENTIFIED BY PASSWORD \'*0123456789012345678901234567890123456789\'', 'ALTER USER USER() IDENTIFIED BY PASSWORD \'text\' REQUIRE SSL', 'ALTER USER USER() IDENTIFIED BY PASSWORD \'text\', v IDENTIFIED BY \'new\'', 'ALTER USER USER() IDENTIFIED BY PASSWORD \'text\' GARBAGE', 'ALTER USER USER() IDENTIFIED BY PASSWORD \'text\' REQUIRE', 'ALTER USER USER() IDENTIFIED BY PASSWORD \'text\' /* trailing */', 'CREATE PROCEDURE p0() BEGIN ALTER USER u IDENTIFIED BY PASSWORD \'text\'; END', 'CREATE PROCEDURE p1() BEGIN IF 0 THEN ALTER USER u IDENTIFIED BY PASSWORD \'text\'; END IF; END', 'CREATE PROCEDURE p2() BEGIN IF 0 THEN ALTER USER missing IDENTIFIED BY PASSWORD \'*0123456789012345678901234567890123456789\'; END IF; END', 'CREATE PROCEDURE p3() BEGIN SELECT BINARY 1; ALTER USER u IDENTIFIED BY PASSWORD \'text\'; END', 'ALTER USER u IDENTIFIED WITH mysql_native_password AS \'*0123456789012345678901234567890123456789\'', 'ALTER USER missing IDENTIFIED WITH mysql_native_password AS \'*0123456789012345678901234567890123456789\'', 'ALTER USER IF EXISTS missing IDENTIFIED BY \'new\'', 'ALTER USER u IDENTIFIED BY \'new\'', 'CREATE USER w IDENTIFIED BY PASSWORD \'*0123456789012345678901234567890123456789\'', 'GRANT USAGE ON *.* TO w IDENTIFIED BY PASSWORD \'*0123456789012345678901234567890123456789\''];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerSql')]
    public function testAccountAuthenticationPreservesStatementRolesAndInputBoundaries(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);
                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\Account\AlterUser>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('account-alteration-state-20261017');
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $structures = [];
            for ($trial = 0; $trial < 12; $trial++) {
                $name = new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new Name('u' . $trial . '_' . $choices->integer(0, 1000)));
                $hash = in_array($release, ['5.7.44', '8.0.44'], true);
                $identification = new \SqlSemantics\Platform\MySql\Statement\Account\User\Identification($hash ? new Name('mysql_native_password') : null, $hash ? \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::Hash : \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text($hash ? '*' . str_repeat($choices->one(['1', 'A', 'F']), 40) : $choices->one(['secret', '', 'new password'])));
                $structures[] = new \SqlSemantics\Platform\MySql\Statement\Account\AlterUser(false, [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification($name, $identification)]);
            }
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Account\AlterUser> $structures
     */
    #[DataProvider('providerStructures')]
    public function testUnchangedAlterationsResolveTheCurrentAccountHistory(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $observations = array_map(static function (\SqlSemantics\Platform\MySql\Statement\Account\AlterUser $alteration) use ($native, $session): array {
                $sql = (new \Fuzz\Structure\Reference())->sql($alteration, $session->semantics()->profile());
                $user = $alteration->users[0];
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification::class, $user);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\AccountName::class, $user->user);
                $name = $user->user->user->value;
                $history = array_map(static function (string $change) use ($native, $session, $alteration, $sql): array {
                    $native->query($change);
                    $session->query($change);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $alteration)];
                }, ['SET @unrelated=1', 'CREATE USER ' . $name, 'DROP USER ' . $name]);
                self::assertSame([1396, null, 1396], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                return $history;
            }, $structures);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
