<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversNothing]
#[Medium]
final class ReplicationLogSpellingTest extends TestCase
{
    #[DataProvider('providerStatements')]
    public function testAnalysisRetainsTheLogWordInStandaloneAndStoredRequests(string $release, string $sql): void
    {
        self::assertSame($sql, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0'] as $version) {
            foreach (['MASTER', 'BINARY'] as $word) {
                $sql = "SHOW $word LOGS";
                yield "$version $sql" => ['mysql-' . $version, $sql];
                foreach (["TO 'binlog.000002'", "BEFORE '2024-01-01'"] as $option) {
                    $sql = "PURGE $word LOGS $option";
                    yield "$version $sql" => ['mysql-' . $version, $sql];
                }
                $sql = "CREATE PROCEDURE p() PURGE $word LOGS BEFORE ROW(1, 2)";
                yield "$version $sql" => ['mysql-' . $version, $sql];
            }
        }
    }
}
