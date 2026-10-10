<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class PreparedDiagnosticsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['SELECT !1', "SELECT BINARY 'a'", 'SELECT SQL_CALC_FOUND_ROWS 1', 'SELECT SRID(NULL)', 'SELECT DIMENSION(NULL)', "SELECT NAME_CONST('x', TRUE)", 'SELECT 1'] as $sql) {
            yield $sql => [$sql];
        }
    }

    #[DataProvider('providerStatements')]
    public function testPreparedDiagnosticsMatchTheServer(string $sql): void
    {
        self::assertSame(self::providerObservation($sql, false), self::providerObservation($sql, true));
    }

    /**
     * @return list<mixed>
     */
    public static function providerObservation(string $sql, bool $memory): array
    {
        [$target] = Servers::shared();
        $pdo = new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]);
        $previous = $pdo->query("SELECT CAST('bad' AS UNSIGNED)");
        self::assertNotFalse($previous);
        $previous->closeCursor();
        $warnings = static function () use ($pdo): array {
            $query = $pdo->query('SHOW WARNINGS');
            self::assertNotFalse($query);

            return $query->fetchAll(PDO::FETCH_NUM);
        };
        $observed = [];
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $statement = $pdo->prepare($sql);
            self::assertNotFalse($statement);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $observed[] = $warnings();
            foreach ([1, 2] as $execution) {
                $statement->execute();
                $observed[] = $statement->fetchAll(PDO::FETCH_NUM);
                $statement->closeCursor();
                $observed[] = $warnings();
            }
        } catch (PDOException $error) {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $observed[] = $error->errorInfo;
            $observed[] = $warnings();
        }

        return $observed;
    }
}
