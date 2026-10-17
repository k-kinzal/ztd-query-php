<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class PasswordNoticesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', "CREATE USER u@localhost IDENTIFIED BY 'pw'"];
        foreach (["OLD_PASSWORD('text')", "OLD_PASSWORD('')", "PASSWORD('text')", "'invalid'"] as $value) {
            array_push($statements, 'SET PASSWORD FOR u@localhost = ' . $value, 'SET @a=1, PASSWORD FOR u@localhost = ' . $value, 'DROP PROCEDURE IF EXISTS p', 'CREATE PROCEDURE p() BEGIN IF @run THEN SET PASSWORD FOR u@localhost = ' . $value . '; END IF; END', 'SET @run=0', 'DO 1 COLLATE missing', 'CALL p()', 'CALL p()', "ALTER PROCEDURE p COMMENT 'again'", 'DO 1 COLLATE missing', 'CALL p()', 'SET @run=1', 'CALL p()');
        }
        array_push($statements, "SET PASSWORD = OLD_PASSWORD('text'),", "SET PASSWORD = OLD_PASSWORD('text') xyz", "SET @a=1, PASSWORD = OLD_PASSWORD('text'), @b=?", 'SET PASSWORD = OLD_PASSWORD(?)');
        foreach (['mysql_old_password', 'mysql_native_password', 'sha256_password'] as $index => $plugin) {
            $statements[] = 'CREATE USER u' . $index . '@localhost IDENTIFIED WITH ' . $plugin;
            foreach (["''", "OLD_PASSWORD('text')", "PASSWORD('text')", "'abcdefghABCDEFGH'", "'0123456789abcdef'", "'*0000000000000000000000000000000000000000'", "'bad'"] as $value) {
                $statements[] = 'SET PASSWORD FOR u' . $index . '@localhost=' . $value;
            }
        }
        yield '5.6.51' => ['5.6.51', $statements];
    }

    /**
     * @param list<string> $statements Typed literals, dependent operands and reached/skipped stored instructions
     */
    #[DataProvider('providerReleases')]
    public function testPasswordNoticesFollowDefinitionsAndTheirCachedLoads(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $catalog = $native->query('SELECT COUNT(*) FROM mysql.time_zone_name');
            self::assertInstanceOf(mysqli_result::class, $catalog);
            $row = $catalog->fetch_row();
            self::assertIsArray($row);
            self::assertArrayHasKey(0, $row);
            self::assertIsString($row[0]);
            $server = Server::start($release, namedTimeZones: (int) $row[0] > 0);
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
     * @return iterable<string, array{list<\SqlSemantics\Platform\MySql\Statement\Account\SetPassword>}>
     */
    public static function providerDirectRequests(): iterable
    {
        $choices = new \Fuzz\Structure\Choices('password-recipient-20261017');
        $statements = [];
        for ($trial = 0; $trial < 40; $trial++) {
            $statements[] = new \SqlSemantics\Platform\MySql\Statement\Account\SetPassword(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('u'), new \SqlSemantics\Statement\Identifier\Name('localhost')), new \SqlSemantics\Platform\MySql\Statement\Account\Password(new \SqlSemantics\Platform\MySql\Statement\Literal\Text((string) $choices->integer(0, 100000)), \SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction::OldPassword));
        }
        yield 'recipient-plugin' => [$statements];
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Account\SetPassword> $statements
     */
    #[DataProvider('providerDirectRequests')]
    public function testDirectRequestsMatchNativeRecipientTransitions(array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql('5.6.51'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new \MySqlMemory\Instance('5.6.51'))->connect();
            $observations = array_map(static function (\SqlSemantics\Platform\MySql\Statement\Account\SetPassword $statement) use ($native, $session): array {
                $sql = (new \Fuzz\Structure\Reference())->sql($statement, $session->semantics()->profile());
                $session->text = 'unrelated invalid input';
                $expected = [serialize((new Native())->observe($native, $sql))];
                $actual = [serialize((new \Fuzz\Structure\Audit\Memory())->observe($session, $statement))];
                $native->query('CREATE USER u@localhost IDENTIFIED WITH mysql_old_password');
                $session->query('CREATE USER u@localhost IDENTIFIED WITH mysql_old_password');
                $session->text = 'unrelated invalid input';
                $expected[] = serialize((new Native())->observe($native, $sql));
                $actual[] = serialize((new \Fuzz\Structure\Audit\Memory())->observe($session, $statement));
                $native->query('DROP USER u@localhost');
                $native->query("CREATE USER u@localhost IDENTIFIED BY 'pw'");
                $session->query("DROP USER u@localhost; CREATE USER u@localhost IDENTIFIED BY 'pw'");
                $session->text = 'unrelated invalid input';
                $expected[] = serialize((new Native())->observe($native, $sql));
                $actual[] = serialize((new \Fuzz\Structure\Audit\Memory())->observe($session, $statement));
                $native->query('DROP USER u@localhost');
                $session->query('DROP USER u@localhost');
                return [serialize($expected), serialize($actual)];
            }, $statements);
            self::assertSame(array_column($observations, 0), array_column($observations, 1));
            $native->close();
        } finally {
            $container->stop();
        }
    }

}
