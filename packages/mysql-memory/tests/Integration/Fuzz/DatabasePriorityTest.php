<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use mysqli;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DatabasePriorityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $requests = [
            'DELETE x FROM priority_domain.t', 'DELETE t FROM priority_domain.t',
            'DELETE t FROM priority_domain.t AS t', 'DELETE t,t FROM priority_domain.t',
            'DELETE priority_domain.t FROM priority_domain.t',
            'DELETE x FROM (SELECT 1) AS d', 'DELETE d FROM (SELECT 1) AS d',
            'DELETE x FROM (SELECT HIGH_PRIORITY 1) AS d',
            'EXPLAIN DELETE x FROM priority_domain.t',
            'SHOW FUNCTION CODE f', 'SHOW FUNCTION CODE priority_domain.f',
            'SHOW PROCEDURE CODE p', 'SHOW PROCEDURE CODE priority_domain.p',
            'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SIGNAL c',
            'CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT BINARY 1',
            "DELETE t FROM priority_domain.t WHERE N'0'",
            "DELETE t FROM priority_domain.t WHERE CONVERT('0' USING utf8)",
            "DELETE priority_domain.t FROM priority_domain.t WHERE N'0'",
            "EXPLAIN DELETE t FROM priority_domain.t WHERE N'0'",
            'EXPLAIN DELETE t FROM priority_domain.t WHERE FALSE',
            'EXPLAIN DELETE t FROM priority_domain.t WHERE NULL',
            'EXPLAIN DELETE t FROM priority_domain.t WHERE 1=2',
            "EXPLAIN DELETE x FROM priority_domain.t AS x WHERE N'0'",
            'EXPLAIN DELETE LOW_PRIORITY IGNORE t FROM priority_domain.t WHERE 0',
            'EXPLAIN DELETE FROM priority_domain.t WHERE 0',
            "DELETE t FROM (SELECT HIGH_PRIORITY N'a') AS d",
            "DELETE t FROM (SELECT @@nonexistingvariable,N'a') AS d",
            "DELETE t FROM (SELECT SQL_CALC_FOUND_ROWS N'a') AS d",
            "DELETE t FROM (SELECT CONVERT(N'a' USING missing_charset)) AS d",
            "DELETE t FROM priority_domain.t WHERE CONVERT(N'a' USING missing_charset)",
            "CREATE PROCEDURE p() SELECT N'a'",
            "CREATE FUNCTION f() RETURNS CHAR(1) RETURN N'a'",
            "CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT N'a'",
        ];
        $inputs = ['CREATE DATABASE priority_domain', 'CREATE TABLE priority_domain.t(a INT)', ...$requests, 'USE priority_domain', 'INSERT INTO t VALUES(1),(2)', ...$requests];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $inputs];
        }
    }

    /**
     * @param list<string> $statements Object names before and after selecting their implicit database
     */
    #[DataProvider('providerStatements')]
    public function testDefaultDatabasePrecedesLaterObjectDiagnostics(string $release, array $statements): void
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

    /**
     * @param list<string> $statements The SQL-path companion cases
     */
    #[DataProvider('providerStatements')]
    public function testDirectDeleteKeepsNoticesAcrossDatabaseAndTableStates(string $release, array $statements): void
    {
        self::assertNotEmpty($statements);
        $table = new QualifiedName(new Name('t'), new Name('priority_domain'));
        $query = new M\Dml\MultipleDelete(null, [], new M\Dml\Target\DeletionInput([new M\Relation\TableReference($table)], [new QualifiedName(new Name('t'))]), M\Dml\MultipleDeleteForm::BeforeFrom, new M\Literal\StringLiteral(['0'], national: true));
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new \MySqlMemory\Instance($release))->connect();
            $states = [['CREATE DATABASE priority_domain'], ['USE priority_domain'], ['CREATE TABLE t(a INT)'], ['INSERT INTO t VALUES(1),(2)']];
            $observations = array_map(static function (array $history) use ($native, $session, $query): array {
                array_map(static function (string $sql) use ($native, $session): void {
                    $native->query($sql);
                    $session->query($sql);
                }, $history);
                $sql = (new \Fuzz\Structure\Reference())->sql($query, $session->semantics()->profile());
                $session->text = 'unrelated invalid input';
                return [(new \Fuzz\Structure\Audit\Native())->observe($native, $sql), (new \Fuzz\Structure\Audit\Memory())->observe($session, $query)];
            }, $states);

            self::assertSame(array_map(serialize(...), array_column($observations, 0)), array_map(serialize(...), array_column($observations, 1)), $release);
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
