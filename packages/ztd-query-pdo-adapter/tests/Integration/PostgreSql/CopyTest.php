<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use Container\Endpoint;
use Container\PostgreSql16Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class CopyTest extends TestCase
{
    #[TestWith(['FROM STDIN'])]
    #[TestWith(['TO STDOUT'])]
    public function testExecDelegatesUnsupportedCopyToTheSession(string $direction): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $pdo */
        $pdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $pdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $pdo->exec('CREATE TABLE copy_target (id INTEGER PRIMARY KEY)');
            $pdo->exec('INSERT INTO copy_target VALUES (1)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);
            $ztdPdo->exec('INSERT INTO copy_target VALUES (2)');
            $sql = 'COPY copy_target ' . $direction;

            try {
                $ztdPdo->exec($sql);
                self::fail('Expected the session to reject unsupported COPY SQL.');
            } catch (ZtdPdoException $exception) {
                $databaseException = $exception->getPrevious();
                self::assertInstanceOf(DatabaseException::class, $databaseException);
                $refusal = $databaseException->getPrevious();
                self::assertInstanceOf(UnsupportedSqlException::class, $refusal);
                self::assertSame($sql, $refusal->getSql());
            }

            $physical = $pdo->query('SELECT id FROM copy_target');
            $shadow = $ztdPdo->query('SELECT id FROM copy_target');
            self::assertNotFalse($physical);
            self::assertNotFalse($shadow);
            self::assertSame([1], $physical->fetchAll(PDO::FETCH_COLUMN));
            self::assertSame([2], $shadow->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    #[TestWith(['FROM STDIN'])]
    #[TestWith(['TO STDOUT'])]
    public function testQueryDelegatesUnsupportedCopyToTheSession(string $direction): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $pdo */
        $pdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $pdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $pdo->exec('CREATE TABLE copy_target (id INTEGER PRIMARY KEY)');
            $pdo->exec('INSERT INTO copy_target VALUES (1)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);
            $ztdPdo->exec('INSERT INTO copy_target VALUES (2)');
            $sql = 'COPY copy_target ' . $direction;

            try {
                $ztdPdo->query($sql);
                self::fail('Expected the session to reject unsupported COPY SQL.');
            } catch (ZtdPdoException $exception) {
                $databaseException = $exception->getPrevious();
                self::assertInstanceOf(DatabaseException::class, $databaseException);
                $refusal = $databaseException->getPrevious();
                self::assertInstanceOf(UnsupportedSqlException::class, $refusal);
                self::assertSame($sql, $refusal->getSql());
            }

            $physical = $pdo->query('SELECT id FROM copy_target');
            $shadow = $ztdPdo->query('SELECT id FROM copy_target');
            self::assertNotFalse($physical);
            self::assertNotFalse($shadow);
            self::assertSame([1], $physical->fetchAll(PDO::FETCH_COLUMN));
            self::assertSame([2], $shadow->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    #[TestWith(['FROM STDIN'])]
    #[TestWith(['TO STDOUT'])]
    public function testPrepareDelegatesUnsupportedCopyToTheSession(string $direction): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $pdo */
        $pdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $pdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $pdo->exec('CREATE TABLE copy_target (id INTEGER PRIMARY KEY)');
            $pdo->exec('INSERT INTO copy_target VALUES (1)');
            $ztdPdo = ZtdPdo::fromPdo($pdo);
            $ztdPdo->exec('INSERT INTO copy_target VALUES (2)');
            $sql = 'COPY copy_target ' . $direction;

            try {
                $ztdPdo->prepare($sql);
                self::fail('Expected the session to reject unsupported COPY SQL.');
            } catch (ZtdPdoException $exception) {
                $databaseException = $exception->getPrevious();
                self::assertInstanceOf(DatabaseException::class, $databaseException);
                $refusal = $databaseException->getPrevious();
                self::assertInstanceOf(UnsupportedSqlException::class, $refusal);
                self::assertSame($sql, $refusal->getSql());
            }

            $physical = $pdo->query('SELECT id FROM copy_target');
            $shadow = $ztdPdo->query('SELECT id FROM copy_target');
            self::assertNotFalse($physical);
            self::assertNotFalse($shadow);
            self::assertSame([1], $physical->fetchAll(PDO::FETCH_COLUMN));
            self::assertSame([2], $shadow->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }

    #[TestWith(['FROM STDIN'])]
    #[TestWith(['TO STDOUT'])]
    public function testStandardPdoMethodsHonorTheSessionsIgnorePolicyForCopy(string $direction): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $pdo */
        $pdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $pdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $pdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $pdo->exec('CREATE TABLE copy_target (id INTEGER PRIMARY KEY)');
            $pdo->exec('INSERT INTO copy_target VALUES (1)');
            $ztdPdo = ZtdPdo::fromPdo($pdo, new ZtdConfig(unsupportedBehavior: UnsupportedSqlBehavior::Ignore));
            $ztdPdo->exec('INSERT INTO copy_target VALUES (2)');

            $sql = 'COPY copy_target ' . $direction;
            self::assertSame(0, $ztdPdo->exec($sql));
            self::assertFalse($ztdPdo->query($sql));
            $statement = $ztdPdo->prepare($sql);
            self::assertNotFalse($statement);
            self::assertFalse($statement->execute());

            $physical = $pdo->query('SELECT id FROM copy_target');
            $shadow = $ztdPdo->query('SELECT id FROM copy_target');
            self::assertNotFalse($physical);
            self::assertNotFalse($shadow);
            self::assertSame([1], $physical->fetchAll(PDO::FETCH_COLUMN));
            self::assertSame([2], $shadow->fetchAll(PDO::FETCH_COLUMN));
        } finally {
            $pdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
