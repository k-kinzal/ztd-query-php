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
final class TypePrefixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE TABLE t(a TEXT BINARY, b INT DEFAULT ?)',
            'CREATE TABLE t(a CHAR(2) BINARY, b INT DEFAULT ?)',
            'CREATE TABLE t(a TEXT BINARY CHARACTER SET missing, b INT DEFAULT ?)',
            'CREATE TABLE t(a TEXT ASCII BINARY, b INT DEFAULT ?)',
            'CREATE TABLE t(a TEXT BINARY ASCII, b INT DEFAULT ?)',
            'CREATE TABLE t(a TEXT UNICODE BINARY, b INT DEFAULT ?)',
            'CREATE TABLE t(a TEXT BINARY UNICODE, b INT DEFAULT ?)',
            'CREATE TABLE t(a TEXT BYTE, b INT DEFAULT ?)',
            'CREATE TABLE t(a BINARY(2), b INT DEFAULT ?)',
            'CREATE TABLE t(a INT BINARY, b INT DEFAULT ?)',
            'CREATE TABLE t(a TEXT CHARACTER SET utf8mb4 BINARY, b INT DEFAULT ?)',
            'ALTER TABLE t CHANGE a b MEDIUMTEXT BINARY GENERATED ALWAYS AS (?)',
            'CREATE TABLE t(a TEXT BINARY /* comment */) ?',
];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The operand completion boundaries to compare
     */
    #[DataProvider('providerReleases')]
    public function testCompletedAttributesPreserveTheirWarnings(string $release, array $statements): void
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
