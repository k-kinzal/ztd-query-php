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
final class IndexNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['PRIMARY', 'primary', '', 'a '] as $number => $name) {
            foreach (['CREATE INDEX `' . $name . '` ON t(a)', 'ALTER TABLE t ADD KEY `' . $name . '`(a)', 'CREATE TABLE IF NOT EXISTS t(a INT, KEY `' . $name . '`(a))', 'CREATE TABLE IF NOT EXISTS t(KEY `' . $name . '`(a)) AS SELECT 1 AS a'] as $form => $body) {
                $procedure = 'p_' . $number . '_' . $form;
                array_push($statements, 'DROP TABLE IF EXISTS t', 'CREATE PROCEDURE ' . $procedure . '() BEGIN IF @run THEN ' . $body . '; END IF; END', 'SET @run=0', 'CALL ' . $procedure . '()', 'SET @run=1', 'CALL ' . $procedure . '()', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(7)', 'CALL ' . $procedure . '()', 'SET @run=0', 'CALL ' . $procedure . '()', $body, 'DROP TABLE t', $body);
            }
        }
        array_push($statements, 'CREATE TABLE t(a INT)', 'CREATE INDEX `PRIMARY` ON t(missing)', 'CREATE INDEX `PRIMARY` ON absent(a)', 'CREATE TABLE IF NOT EXISTS t(a INT, KEY `PRIMARY`(missing))', 'ALTER TABLE t ADD KEY `PRIMARY`(missing)', 'CREATE TABLE fresh(a INT, CONSTRAINT `PRIMARY` UNIQUE(a))', 'CREATE TABLE good(a INT, CONSTRAINT `PRIMARY` UNIQUE valid(a))', 'CREATE TABLE pk(a INT, CONSTRAINT `` PRIMARY KEY `a `(a))');
        foreach (['ROW(missing,2)', 'RAND()', "CAST(FALSE AT TIME ZONE 't' AS DATETIME)", '(SELECT a FROM absent)'] as $operand) {
            array_push($statements, 'CREATE INDEX `PRIMARY` ON t((' . $operand . '))', 'ALTER TABLE t ADD KEY `PRIMARY`((' . $operand . '))', 'CREATE TABLE fresh(a INT, KEY `PRIMARY`((' . $operand . ')))');
        }
        array_push($statements, 'DROP TABLE t', 'CREATE PROCEDURE retry_index() CREATE TABLE t(a INT, KEY `PRIMARY`(a))', 'CALL retry_index()', 'CALL retry_index()', 'CREATE TABLE t(a INT)', 'CALL retry_index()', 'CALL retry_index()', 'DROP TABLE t', 'CALL retry_index()', 'CREATE TABLE t(a INT)', 'CALL retry_index()', 'ALTER TABLE t ADD COLUMN b INT', 'CALL retry_index()', 'CALL retry_index()');
        array_push($statements, 'ALTER TABLE t ADD KEY ordinary(missing), ADD KEY `PRIMARY`(a)', 'ALTER TABLE t ADD KEY `PRIMARY`(a), ADD KEY ordinary(missing)', 'ALTER TABLE t ADD a INT, ADD KEY `PRIMARY`(a)', 'ALTER TABLE t DROP COLUMN absent, ADD KEY `PRIMARY`(a)', 'ALTER TABLE t ADD KEY ordinary(a(4)), ADD KEY `PRIMARY`(a)', 'CREATE TABLE fresh(a INT,KEY ordinary(missing),KEY `PRIMARY`(a))', 'CREATE TABLE fresh(a INT,a INT,KEY `PRIMARY`(a))');
        array_push($statements, 'CREATE TABLE fresh(KEY ordinary(missing),KEY `PRIMARY`(a)) AS SELECT 1 AS a', 'CREATE TABLE fresh(KEY `PRIMARY`(a),KEY ordinary(missing)) AS SELECT 1 AS a');
        $statements[] = "SHOW SESSION STATUS WHERE Variable_name IN ('Com_create_index','Com_alter_table','Com_create_table')";
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Skipped and reached instructions with missing and populated targets
     */
    #[DataProvider('providerReleases')]
    public function testIndexNamesPreserveDeclarationAndInvocationPriority(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, parameters: $prepared && str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0, prepared: $prepared && (str_starts_with($sql, 'CREATE INDEX') || str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS') || str_starts_with($sql, 'CALL') || str_starts_with($sql, 'ALTER TABLE')))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, parameters: $prepared && str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0, prepared: $prepared && (str_starts_with($sql, 'CREATE INDEX') || str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS') || str_starts_with($sql, 'CALL') || str_starts_with($sql, 'ALTER TABLE')))), $statements);

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
