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
final class FunctionalIndexKindTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['InnoDB', 'MyISAM', 'MEMORY'] as $engine) {
            array_push($statements, 'DROP TABLE IF EXISTS t', 'CREATE TABLE t(a INT,b VARCHAR(20)) ENGINE=' . $engine, 'INSERT INTO t VALUES(7,\'data\')');
            foreach (['FULLTEXT', 'SPATIAL', 'PRIMARY KEY'] as $kind) {
                foreach (['(1+1)', '(ROW(1,2))', '(RAND())', '(missing+1)', "(CAST(FALSE AT TIME ZONE 't' AS DATETIME))", '((SELECT a FROM absent))', 'a,(1+1)', 'b,(1+1)', '(1+1),a', 'missing,(1+1)'] as $parts) {
                    array_push($statements, 'ALTER TABLE t ADD ' . $kind . ' k(' . $parts . ')', 'CREATE TABLE fresh(a INT,b VARCHAR(20),' . $kind . ' k(' . $parts . ')) ENGINE=' . $engine, 'CREATE TABLE IF NOT EXISTS t(a INT,b VARCHAR(20),' . $kind . ' k(' . $parts . ')) ENGINE=' . $engine);
                    if ($kind !== 'PRIMARY KEY') {
                        $statements[] = 'CREATE ' . $kind . ' INDEX k ON t(' . $parts . ')';
                    }
                }
            }
        }
        foreach (['FULLTEXT', 'SPATIAL', 'PRIMARY KEY'] as $number => $kind) {
            foreach (['KEY ordinary(missing),' . $kind . ' k((1+1))', $kind . ' k((1+1)),KEY ordinary(missing)', 'KEY `PRIMARY`(a),' . $kind . ' k((1+1))', $kind . ' k((1+1)),KEY `PRIMARY`(a)', $kind . ' `PRIMARY`((1+1))'] as $keys) {
                array_push($statements, 'CREATE TABLE fresh(a INT,' . $keys . ')', 'ALTER TABLE t ADD ' . str_replace('),', '), ADD ', $keys));
            }
            foreach (['ALTER TABLE t ADD ' . $kind . ' k((1+1))', 'CREATE TABLE IF NOT EXISTS t(a INT,' . $kind . ' k((1+1)))'] as $form => $body) {
                $procedure = 'p_' . $number . '_' . $form;
                array_push($statements, 'DROP TABLE t', 'CREATE PROCEDURE ' . $procedure . '() BEGIN IF @run THEN ' . $body . '; END IF; END', 'SET @run=0', 'CALL ' . $procedure . '()', 'SET @run=1', 'CALL ' . $procedure . '()', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(7)', 'CALL ' . $procedure . '()', 'SET @run=0', 'CALL ' . $procedure . '()');
            }
            $statements[] = 'CREATE TABLE fresh(a INT,a INT,' . $kind . ' k((1+1)))';
        }
        foreach (['CHAR(10)', 'VARCHAR(20)', 'TEXT', 'BLOB', 'VARBINARY(20)', 'BINARY(10)', "ENUM('x','y')", "SET('x','y')", 'INT', 'JSON', 'DATE', 'POINT', 'TEXT CHARACTER SET ucs2', 'TEXT CHARACTER SET utf16', 'TEXT CHARACTER SET utf16le', 'TEXT CHARACTER SET utf32', 'TEXT CHARACTER SET ascii', 'TEXT COLLATE utf8mb4_bin'] as $type) {
            array_push($statements, 'DROP TABLE t', 'CREATE TABLE t(a ' . $type . ')', 'CREATE FULLTEXT INDEX k ON t(a,(1+1))', 'ALTER TABLE t ADD FULLTEXT k(a,(1+1))', 'CREATE TABLE fresh(a ' . $type . ',FULLTEXT k(a,(1+1)))');
        }
        $statements[] = "SHOW SESSION STATUS WHERE Variable_name IN ('Com_create_index','Com_alter_table','Com_create_table')";
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Skipped and reached instructions with missing and populated targets
     */
    #[DataProvider('providerReleases')]
    public function testFunctionalKindsPreserveDeclarationAndInvocationPriority(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, parameters: $prepared && str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0, prepared: $prepared && ((str_starts_with($sql, 'CREATE SPATIAL INDEX') || str_starts_with($sql, 'CREATE FULLTEXT INDEX')) || str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS') || str_starts_with($sql, 'CALL') || str_starts_with($sql, 'ALTER TABLE')))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, parameters: $prepared && str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0, prepared: $prepared && ((str_starts_with($sql, 'CREATE SPATIAL INDEX') || str_starts_with($sql, 'CREATE FULLTEXT INDEX')) || str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS') || str_starts_with($sql, 'CALL') || str_starts_with($sql, 'ALTER TABLE')))), $statements);

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
