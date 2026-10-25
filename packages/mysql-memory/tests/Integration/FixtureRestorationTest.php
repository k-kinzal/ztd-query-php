<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Fixture\Baseline;
use Fuzz\Fixture\Servers;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
#[Large]
final class FixtureRestorationTest extends TestCase
{
    #[DataProvider('providerErrorModes')]
    public function testACatalogReadFailureCannotBecomeAnEmptyInventory(int $errorMode): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => $errorMode]);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cannot read a differential fixture');

            (new Servers())->rows($pdo, 'SELECT * FROM absent_fixture.absent_table');
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerErrorModes(): iterable
    {
        yield 'exceptions' => [PDO::ERRMODE_EXCEPTION];
        yield 'silent driver errors' => [PDO::ERRMODE_SILENT];
    }

    public function testRestorationDiscoversObjectsOnTheConnectionItRestores(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            (new Servers())->guard($pdo, '8.4.7', 'root');
            $guard = new PDO($server->dsn(), Servers::GUARD, 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $baseline = new Baseline($pdo, '8.4.7');
            $pdo->exec('CREATE DATABASE leaked_database');
            $pdo->exec("SET GLOBAL sql_mode = 'PIPES_AS_CONCAT'");
            $baseline->restore($guard);

            self::assertSame([], (new Servers())->rows($guard, "SHOW DATABASES LIKE 'leaked_database'"));
            self::assertSame($baseline->globals['sql_mode'], (new Servers())->globals($guard, '8.4.7')['sql_mode']);
            $baseline->assertRestored($guard);
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    public function testTheRestorationPostconditionRejectsAnUnrestoredGlobal(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $baseline = new Baseline($pdo, '8.4.7');
            $pdo->exec("SET GLOBAL sql_mode = 'PIPES_AS_CONCAT'");
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('The differential global variables were not restored.');

            $baseline->assertRestored($pdo);
        } finally {
            $server->stop();
        }
    }

    public function testCredentialAgeSurvivesAccountRestoration(): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            (new Servers())->guard($pdo, '8.4.7', 'root');
            $guard = new PDO($server->dsn(), Servers::GUARD, 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET timestamp = 1600000000');
            $pdo->exec("ALTER USER CURRENT_USER IDENTIFIED BY 'fixture'");
            $pdo->exec('SET timestamp = 0');
            $baseline = new Baseline($pdo, '8.4.7');
            self::assertSame(1600000000, $baseline->passwordChanged);

            $baseline->restore($guard);
            self::assertSame(1600000000, $baseline->passwordChanged($guard));
            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }
}
