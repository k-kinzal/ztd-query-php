<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class VersionedGrammarTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version, !str_starts_with($version, '5.')];
        }
    }

    #[DataProvider('providerReleases')]
    public function testCommonTableExpressionsFollowTheSelectedGrammar(string $version, bool $commonTables): void
    {
        $database = MemoryDatabase::start($version);
        try {
            $pdo = new PDO($database->dsn(), 'root', '');
            try {
                $rows = $pdo->query('WITH c AS (SELECT 42 AS n) SELECT n FROM c');
                self::assertNotFalse($rows);
                self::assertTrue($commonTables);
                self::assertSame(42, $rows->fetchColumn());
            } catch (PDOException $error) {
                self::assertFalse($commonTables);
                self::assertSame(1064, $error->errorInfo[1] ?? null);
            }
        } finally {
            $database->stop();
        }
    }
}
