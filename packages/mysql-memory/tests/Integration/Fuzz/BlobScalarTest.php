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
final class BlobScalarTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'] as $type) {
            $setup = "SET NAMES utf8mb4; CREATE TEMPORARY TABLE scalar_blob(b {$type} CHARACTER SET utf8mb4); INSERT INTO scalar_blob VALUES('x'); ";
            yield $type . ' column' => [$setup . 'SELECT (SELECT b FROM scalar_blob)'];
        }
        $setup = "SET NAMES utf8mb4; CREATE TEMPORARY TABLE scalar_blob(b TEXT CHARACTER SET utf8mb4); INSERT INTO scalar_blob VALUES('x'); ";
        yield 'parenthesized column' => [$setup . 'SELECT (SELECT (b) FROM scalar_blob)'];
        yield 'star column' => [$setup . 'SELECT (SELECT * FROM scalar_blob)'];
        yield 'computed string' => [$setup . "SELECT (SELECT CONCAT(b,'') FROM scalar_blob)"];
        yield 'computed cast' => [$setup . 'SELECT (SELECT CAST(b AS CHAR(17000)) FROM scalar_blob)'];
        yield 'unconverted column' => [$setup . 'SET character_set_results=NULL; SELECT (SELECT b FROM scalar_blob)'];
        yield 'wide character column' => ["SET NAMES utf8mb4; CREATE TEMPORARY TABLE scalar_blob(b TEXT CHARACTER SET utf16); INSERT INTO scalar_blob VALUES('x'); SELECT (SELECT b FROM scalar_blob)"];
        yield 'binary column' => ["CREATE TEMPORARY TABLE scalar_blob(b BLOB); INSERT INTO scalar_blob VALUES('x'); SELECT (SELECT b FROM scalar_blob)"];
        yield 'computed alias' => [$setup . 'SELECT CAST(b AS CHAR(17000)) AS x,(SELECT (x) FROM DUAL WHERE TRUE) FROM scalar_blob'];
    }

    #[DataProvider('providerStatements')]
    public function testBlobScalarBoundsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
