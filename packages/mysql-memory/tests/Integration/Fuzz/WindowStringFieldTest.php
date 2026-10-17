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
final class WindowStringFieldTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['VARCHAR(511)', 'VARCHAR(512)', 'VARCHAR(513)', 'VARBINARY(513)', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BLOB'] as $type) {
            $setup = "CREATE TEMPORARY TABLE window_strings(n INT,s $type) CHARACTER SET utf8mb4; INSERT INTO window_strings VALUES(1,'a'),(2,'b'); ";
            foreach (['utf8mb4', 'latin1'] as $charset) {
                foreach (['FIRST_VALUE(s) OVER ()', 'MIN(s) OVER ()', '@x:=FIRST_VALUE(s) OVER ()', 'CONCAT(FIRST_VALUE(s) OVER ())', 'MIN(s)', 'MIN(CONCAT(s))', 'DISTINCT CONCAT(MIN(s))', 'MIN(MIN(s)) OVER ()', 'DISTINCT CONCAT(MIN(MIN(s)) OVER ())'] as $expression) {
                    $group = !str_contains($expression, 'OVER') || str_contains($expression, 'MIN(MIN') ? ' GROUP BY n' : '';
                    yield $type . ' ' . $charset . ' ' . $expression => [$setup . 'SET NAMES ' . $charset . '; SELECT ' . $expression . ' AS v FROM window_strings' . $group];
                }
                foreach (['FIRST_VALUE(s) OVER ()', 'MIN(s) OVER ()', '@x:=FIRST_VALUE(s) OVER ()', 'CONCAT(FIRST_VALUE(s) OVER ())', 'MIN(s)', 'MIN(MIN(s)) OVER ()'] as $expression) {
                    $group = !str_contains($expression, 'OVER') || str_contains($expression, 'MIN(MIN') ? ' GROUP BY n' : '';
                    $body = 'SELECT ' . $expression . ' AS v FROM window_strings' . $group;
                    foreach (['derived' => 'SELECT * FROM (' . $body . ') AS d', 'union' => '(' . $body . ') UNION ALL (' . $body . ')'] as $wrapper => $query) {
                        yield $type . ' ' . $charset . ' ' . $expression . ' ' . $wrapper => [$setup . 'SET NAMES ' . $charset . '; ' . $query];
                    }
                }
            }
        }
        yield from self::providerJson();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerJson(): iterable
    {
        $setup = 'CREATE TEMPORARY TABLE window_json(n INT,j JSON); INSERT INTO window_json VALUES(1,JSON_OBJECT("a",1)),(2,JSON_OBJECT("a",2)); ';
        foreach (['utf8mb4', 'latin1'] as $charset) {
            foreach (['@x:=FIRST_VALUE(j) OVER ()', '@x:=LEAD(j) OVER ()', '@x:=JSON_ARRAYAGG(n) OVER ()'] as $expression) {
                $body = 'SELECT ' . $expression . ' AS v FROM window_json';
                yield $charset . ' ' . $expression => [$setup . 'SET NAMES ' . $charset . '; ' . $body];
                yield $charset . ' ' . $expression . ' derived' => [$setup . 'SET NAMES ' . $charset . '; SELECT * FROM (' . $body . ') AS d'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testStoredStringFieldsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
