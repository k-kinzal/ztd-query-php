<?php

declare(strict_types=1);

namespace Tests\Integration\Evaluation;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class JsonNullTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<list<string>>}>
     */
    public static function providerLegacyJsonNull(): iterable
    {
        $six = [['Warning', '3156', 'Invalid JSON value for CAST to INTEGER from column ? at row 6']];
        $five = [['Warning', '3156', 'Invalid JSON value for CAST to INTEGER from column ? at row 5']];
        yield 'outer array' => ['5.7.44', 'SELECT COUNT(*) FROM t HAVING (SELECT JSON_ARRAYAGG(t.a)) IS NOT NULL', $six];
        yield 'outer null test' => ['5.7.44', 'SELECT COUNT(*) FROM t HAVING (SELECT JSON_ARRAYAGG(t.a)) IS NULL', $six];
        yield 'outer object' => ['5.7.44', 'SELECT COUNT(*) FROM t HAVING (SELECT JSON_OBJECTAGG(t.id,t.a)) IS NOT NULL', $six];
        yield 'independent array' => ['5.7.44', 'SELECT COUNT(*) FROM t HAVING (SELECT JSON_ARRAYAGG(a) FROM u) IS NOT NULL', $five];
        yield 'selected array' => ['5.7.44', 'SELECT (SELECT JSON_ARRAYAGG(a) FROM t) IS NOT NULL', $six];
        yield 'direct aggregate' => ['5.7.44', 'SELECT JSON_ARRAYAGG(a) IS NOT NULL FROM t', []];
        yield 'reduced constructor' => ['5.7.44', 'SELECT (SELECT JSON_ARRAY(a)) IS NOT NULL FROM t', []];
        yield 'empty scalar' => ['5.7.44', 'SELECT (SELECT JSON_ARRAYAGG(a) FROM t WHERE FALSE) IS NOT NULL', []];
        yield 'modern scalar' => ['8.4.7', 'SELECT (SELECT JSON_ARRAYAGG(a) FROM t) IS NOT NULL', []];
    }

    /**
     * @param list<list<string>> $expected
     */
    #[DataProvider('providerLegacyJsonNull')]
    public function testLegacyJsonNullWarnsAtTheConsumedInputPosition(string $release, string $sql, array $expected): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(id INT,a INT); CREATE TABLE u(a INT); INSERT INTO t VALUES (1,10),(2,-3),(3,NULL),(4,10),(5,7); INSERT INTO u VALUES (1),(10),(-3),(7)');
        $session->query($sql);
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);

        self::assertSame($expected, $warnings->rows);
    }
}
