<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Evaluation\Compile\Family\NumericReads;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(NumericReads::class)]
#[Small]
final class NumericReadsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacyVersions(): iterable
    {
        yield '5.6' => ['5.6.51'];
        yield '5.7' => ['5.7.44'];
    }

    #[DataProvider('providerLegacyVersions')]
    public function testDoubleComparesLegacyArgumentsBeforeConvertingTheResult(string $version): void
    {
        $session = (new Instance($version))->connect();
        $result = $session->query("SELECT LEAST('11','45','2')+0,ABS(LEAST('12x','z')),ABS(GREATEST('-2x','0'))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '0', '0']], $result->rows);
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: '12x'"], ['Warning', 1292, "Truncated incorrect DOUBLE value: 'z'"], ['Warning', 1292, "Truncated incorrect DOUBLE value: '-2x'"]], $session->diagnostics->conditions);
    }

    #[DataProvider('providerLegacyVersions')]
    public function testDoubleKeepsSelectedStringsIndependentFromTheirNumericSortingReads(string $version): void
    {
        $session = (new Instance($version))->connect();
        $session->query("CREATE DATABASE d;USE d;CREATE TABLE t(s VARCHAR(10));INSERT INTO t VALUES('12x'),('3x'),('-2x')");
        $result = $session->query("SELECT LEAST(s,'z') AS v FROM t ORDER BY ABS(v)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['12x'], ['3x'], ['-2x']], $result->rows);
        self::assertSame(6, count($session->diagnostics->conditions));
    }

    #[DataProvider('providerLegacyVersions')]
    public function testDoublePassesIntoNestedExtremaAndStopsAtStringFunctions(string $version): void
    {
        $session = (new Instance($version))->connect();
        $result = $session->query("SELECT ABS(LEAST(GREATEST('11','2'),'4')),ABS(CONCAT(LEAST('12x','z')))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4', '12']], $result->rows);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testDoublePreservesModernArgumentComparisonAndQuietStringConversion(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LEAST('11','45','2')+0,ABS(LEAST('12x','z')),ABS(GREATEST('-2x','0'))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['11', '12', '0']], $result->rows);
        self::assertSame([], $session->diagnostics->conditions);
    }
}
