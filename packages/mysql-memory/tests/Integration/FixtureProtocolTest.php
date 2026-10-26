<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Fixture\Tables;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversNothing]
#[Large]
final class FixtureProtocolTest extends TestCase
{
    public function testFixtureSqlUsesTextAndTheFollowingInputUsesNativePreparation(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
            $tables = new Tables();
            $tables->execute($connection, 'CREATE DATABASE fixture');
            $tables->execute($connection, 'USE fixture');
            $tables->execute($connection, 'CREATE TABLE t (id INT)');
            $tables->execute($connection, 'INSERT INTO t VALUES (1)');
            self::assertFalse((bool) $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));

            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $before = $connection->query("SHOW SESSION STATUS LIKE 'Com_stmt_prepare'");
            self::assertNotFalse($before);
            self::assertSame([['Com_stmt_prepare', '0']], $before->fetchAll(PDO::FETCH_NUM));
            $before->closeCursor();
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $input = $connection->query('BEGIN');
            self::assertNotFalse($input);
            $input->closeCursor();
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $after = $connection->query("SHOW SESSION STATUS LIKE 'Com_stmt_prepare'");
            self::assertNotFalse($after);
            self::assertSame([['Com_stmt_prepare', '1']], $after->fetchAll(PDO::FETCH_NUM));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    /**
     * @param class-string<Throwable> $exception The failure exposed by the selected PDO error mode
     */
    #[DataProvider('providerErrorModes')]
    public function testFailedSetupRestoresTheSelectedInputProtocol(int $mode, string $exception): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $connection = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => false]);
            try {
                $this->expectException($exception);

                (new Tables())->execute($connection, 'USE absent_fixture');
            } finally {
                self::assertFalse((bool) $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            }
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{int, class-string<Throwable>}>
     */
    public static function providerErrorModes(): iterable
    {
        yield 'exceptions' => [PDO::ERRMODE_EXCEPTION, PDOException::class];
        yield 'silent' => [PDO::ERRMODE_SILENT, RuntimeException::class];
    }
}
