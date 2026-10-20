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
final class DeferredIndexTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['ROW(1,2)', "CAST(FALSE AT TIME ZONE 't' AS DATETIME)", 'ROW(missing,2)', 'ROW(missing(),2)'] as $number => $operand) {
            $index = 'CREATE INDEX i ON t ((' . $operand . '))';
            $alter = 'ALTER TABLE t ADD KEY i ((' . $operand . '))';
            $table = 'CREATE TABLE IF NOT EXISTS t(a INT, KEY i ((' . $operand . ')))';
            foreach ([$index, $alter, $table] as $form => $body) {
                $procedure = 'p_' . $number . '_' . $form;
                array_push(
                    $statements,
                    'DROP TABLE IF EXISTS t',
                    'CREATE PROCEDURE ' . $procedure . '() BEGIN IF @run THEN ' . $body . '; END IF; END',
                    'SET @run=0',
                    'CALL ' . $procedure . '()',
                    'SET @run=1',
                    'CALL ' . $procedure . '()',
                    'CREATE TABLE t(a INT)',
                    'INSERT INTO t VALUES(7)',
                    'CALL ' . $procedure . '()',
                    'SET @run=0',
                    'CALL ' . $procedure . '()',
                    $body,
                    'DROP TABLE t',
                    $body,
                );
            }
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
    public function testRetainedIndexInstructionsPreserveTargetLookupAndInvocationConditions(string $release, array $statements, bool $prepared): void
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
