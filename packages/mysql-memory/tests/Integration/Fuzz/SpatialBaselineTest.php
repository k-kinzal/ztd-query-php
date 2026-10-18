<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class SpatialBaselineTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testRestoreKeepsSpatialMutationsComparableAcrossIndependentInputs(string $release): void
    {
        $previous = getenv('MYSQL_VERSION');
        putenv('MYSQL_VERSION=' . $release);
        try {
            [$target, , $server] = (new Servers())->start(true, true);
            try {
                self::assertNotNull($target->baseline);
                $baseline = $target->baseline->spatial;
                $create = str_replace("CREATE SPATIAL REFERENCE SYSTEM 4326 NAME 'WGS 84'", "CREATE SPATIAL REFERENCE SYSTEM 500001 NAME 'baseline created'", $baseline->definitions[4326]);
                $replace = str_replace("CREATE SPATIAL REFERENCE SYSTEM 4326 NAME 'WGS 84'", "CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 3812 NAME 'baseline replaced'", $baseline->definitions[4326]);
                $validCreate = str_replace('IDENTIFIED BY 4326', 'IDENTIFIED BY 500001', $create);
                $validReplace = str_replace('IDENTIFIED BY 4326', 'IDENTIFIED BY 3812', $replace);
                $comparisons = array_map($target->compare(...), ['DROP SPATIAL REFERENCE SYSTEM 0xee4', $create, $replace, 'DROP SPATIAL REFERENCE SYSTEM 0xee4', $create, $validCreate, $validReplace, $validCreate]);
                self::assertSame(array_fill(0, 8, false), array_column($comparisons, 'volatile'));
                self::assertSame(array_fill(0, 8, null), array_column($comparisons, 'difference'));
                self::assertSame(array_fill(0, 8, []), array_column($comparisons, 'contracts'));
                $native = new PDO($target->native, $target->nativeUser, $target->nativePassword);
                self::assertSame($baseline->signatures, $baseline->read($native));
                $memory = new PDO($target->memory, 'root', '');
                self::assertSame([['3812', 'ETRS89 / Belgian Lambert 2008']], (new Servers())->rows($memory, 'SELECT SRS_ID, SRS_NAME FROM information_schema.ST_SPATIAL_REFERENCE_SYSTEMS WHERE SRS_ID IN (3812,500001)'));
            } finally {
                $server->stop();
                $target->reference?->stop();
            }
        } finally {
            putenv($previous === false ? 'MYSQL_VERSION' : 'MYSQL_VERSION=' . $previous);
        }
    }
}
