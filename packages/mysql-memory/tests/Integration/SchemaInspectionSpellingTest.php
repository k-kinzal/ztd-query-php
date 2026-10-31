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
final class SchemaInspectionSpellingTest extends TestCase
{
    #[DataProvider('providerStatements')]
    public function testAnalysisPreservesEachQualifierThroughFiltering(string $release, string $sql): void
    {
        self::assertSame($sql, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['EVENTS', 'TABLES', 'TRIGGERS', 'TABLE STATUS', 'OPEN TABLES'] as $report) {
                foreach (['FROM', 'IN'] as $word) {
                    $sql = "SHOW $report $word shop LIKE 'a%'";
                    yield "$version $sql" => ['mysql-' . $version, $sql];
                }
            }
            foreach (['FROM', 'IN'] as $table) {
                foreach (['FROM', 'IN'] as $database) {
                    $sql = "SHOW KEYS $table t $database shop WHERE Key_name = 'PRIMARY'";
                    yield "$version $sql" => ['mysql-' . $version, $sql];
                }
            }
        }
    }
}
