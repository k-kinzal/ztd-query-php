<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class BlobMetadataTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'text storage lengths without conversion' => ['SET character_set_results=NULL; CREATE TEMPORARY TABLE bmeta(a TINYTEXT,b TEXT,c MEDIUMTEXT,d LONGTEXT) CHARACTER SET utf8mb4; SELECT * FROM bmeta'];
        yield 'large character cast without conversion' => ['SET character_set_results=NULL; SELECT CAST(a AS CHAR(70000) CHARACTER SET utf8mb4) FROM t1'];
        yield 'aggregate without conversion' => ['SET character_set_results=NULL; SELECT GROUP_CONCAT(CONVERT(a USING utf8mb4)) FROM t1'];
        yield 'varchar remains character sized' => ['SET character_set_results=NULL; SELECT CAST(a AS CHAR(10) CHARACTER SET utf8mb4) FROM t1'];
        yield 'converted text storage lengths' => ['SET NAMES utf8mb4; CREATE TEMPORARY TABLE bmeta(a TINYTEXT,b TEXT,c MEDIUMTEXT,d LONGTEXT) CHARACTER SET utf8mb4; SELECT * FROM bmeta'];
        yield 'hex expression bound' => ['SET NAMES utf8mb4; CREATE TEMPORARY TABLE bmeta(a TEXT CHARACTER SET utf8mb4); SELECT HEX(a) FROM bmeta'];
        yield 'base64 expression bound' => ['SET NAMES utf8mb4; CREATE TEMPORARY TABLE bmeta(a TEXT CHARACTER SET utf8mb4); SELECT TO_BASE64(a) FROM bmeta'];
        yield 'GTID expression bound' => ["SET NAMES utf8mb4; CREATE TEMPORARY TABLE bmeta(a TEXT CHARACTER SET utf8mb4); SELECT GTID_SUBTRACT(a,'') FROM bmeta"];
        yield 'converted large character cast' => ['SET NAMES utf8mb4; SELECT CAST(a AS CHAR(70000) CHARACTER SET utf8mb4) FROM t1'];
    }

    #[DataProvider('providerStatements')]
    public function testRawBlobLengthsAreNotMultipliedAsCharacterCounts(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
