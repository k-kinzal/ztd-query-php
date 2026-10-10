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
final class SridFunctionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['ST_SRID', 'SRID'] as $name) {
            foreach (['Point(1,2)', 'NULL', "''", '1', 'GeometryCollection(Point(1,2))'] as $argument) {
                yield $name . ' ' . $argument => ['SELECT ' . $name . '(' . $argument . '); SHOW WARNINGS'];
            }
            foreach (['0', '1', '4326', '999999', '-1', '4294967296', '1.5', '0.5e0', "'abc'", 'NULL'] as $target) {
                yield $name . ' set ' . $target => ['SELECT HEX(' . $name . '(Point(1,2),' . $target . ')); SHOW WARNINGS'];
            }
        }
        $binaries = [
            'header' => pack('V', 4326) . "\1" . pack('V', 1),
            'nan' => pack('V', 0) . "\1" . pack('V', 1) . pack('ee', NAN, 1.0),
            'empty line' => pack('V', 0) . "\1" . pack('VV', 2, 0),
            'unknown' => pack('V', 1234567) . "\1" . pack('V', 1) . pack('ee', 1.0, 2.0),
        ];
        foreach ($binaries as $label => $bytes) {
            foreach (['', ',0', ',NULL', ',-1'] as $target) {
                yield $label . $target => ["SELECT HEX(ST_SRID(X'" . bin2hex($bytes) . "'" . $target . ')); SHOW WARNINGS'];
            }
        }
        foreach (["'',NULL", "'',-1", 'NULL,-1', "NULL,'abc'", 'Point(1,2),0.5', "'',1", 'NULL,1', '1/0,NULL', "1/0,'abc'", 'Point(1,2),18446744073709551616'] as $arguments) {
            yield 'precedence ' . $arguments => ['SELECT HEX(ST_SRID(' . $arguments . ')); SHOW WARNINGS'];
        }
        $big = bin2hex(pack('V', 0) . "\0" . pack('N', 1) . pack('EE', 1.0, 2.0));
        yield 'big endian getter' => ["SELECT ST_SRID(X'" . $big . "'); SHOW WARNINGS"];
        yield 'big endian setter' => ["SELECT HEX(ST_SRID(X'" . $big . "',4326)); SHOW WARNINGS"];
        foreach (['', 'STRICT_TRANS_TABLES'] as $mode) {
            foreach (['', 'IGNORE'] as $ignore) {
                yield 'unknown write ' . $mode . $ignore => ["SET sql_mode='" . $mode . "'; CREATE TABLE srid_function(a INT UNSIGNED); INSERT " . $ignore . " INTO srid_function SELECT ST_SRID(X'" . bin2hex($binaries['unknown']) . "'); SHOW WARNINGS; SELECT * FROM srid_function"];
            }
        }
        yield 'stored identifier'  => ['CREATE TABLE srid_function(a INT UNSIGNED); INSERT INTO srid_function SELECT ST_SRID(Point(1,2)); SELECT * FROM srid_function'];
    }

    #[DataProvider('providerStatements')]
    public function testSridCallsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
