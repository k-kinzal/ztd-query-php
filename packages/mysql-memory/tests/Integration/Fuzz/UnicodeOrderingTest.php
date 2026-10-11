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
final class UnicodeOrderingTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $pairs = [['ß', 'ss'], ['ß', 's'], ['é', "e\u{301}"], ['l·', 'l'], ["и\u{306}", 'й'], ['🫠', '😀'], ["\u{FDFA}", 'صلى الله عليه وسلم'], ['a', "a\0"]];
        $texts = ['ß', 's', 'ss', 'e', 'é', "e\u{301}", 'l', 'l·', "и\u{306}", 'й', '😀', '🫠', 'a ', 'A', 'a', '', "a\0", "\0"];
        foreach (['utf8mb4_general_ci', 'utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_ci', 'utf8mb4_0900_as_cs'] as $collation) {
            $expressions = [];
            foreach ($pairs as $index => [$left, $right]) {
                $left = "_utf8mb4 X'" . bin2hex($left) . "' COLLATE " . $collation;
                $right = "_utf8mb4 X'" . bin2hex($right) . "' COLLATE " . $collation;
                $expressions[] = 'STRCMP(' . $left . ',' . $right . ') AS c' . $index;
            }
            yield $collation . ' comparisons' => ['SELECT ' . implode(',', $expressions) . '; SHOW WARNINGS'];
            $rows = [];
            foreach ($texts as $index => $text) {
                $rows[] = '(' . $index . ",_utf8mb4 X'" . bin2hex($text) . "')";
            }
            $setup = 'CREATE TABLE unicode_order (id INT PRIMARY KEY, v VARCHAR(64) CHARACTER SET utf8mb4 COLLATE ' . $collation . '); INSERT INTO unicode_order VALUES ' . implode(',', $rows) . '; ';
            yield $collation . ' sort' => [$setup . 'SELECT id, v FROM unicode_order ORDER BY v, id'];
            yield $collation . ' groups' => [$setup . 'SELECT MIN(id), COUNT(*) FROM unicode_order GROUP BY v ORDER BY v; SELECT COUNT(DISTINCT v) FROM unicode_order'];
            yield $collation . ' join keys' => [$setup . 'SELECT a.id, b.id FROM unicode_order a JOIN unicode_order b ON a.v=b.v ORDER BY a.id,b.id'];
            yield $collation . ' dependent join keys' => [$setup . 'SELECT a.id, b.id FROM unicode_order a JOIN unicode_order b ON a.id=b.id ORDER BY a.id,b.id'];
            yield $collation . ' constant join keys' => [$setup . 'SELECT a.id, b.id FROM unicode_order a JOIN unicode_order b ON a.v=b.v WHERE a.id=0 ORDER BY a.id,b.id'];
            yield $collation . ' empty join keys' => [$setup . 'SELECT a.id, b.id FROM unicode_order a JOIN unicode_order b ON a.v=b.v ORDER BY a.id,b.id LIMIT 0'];
            yield $collation . ' unique values' => ['CREATE TABLE unicode_unique (v VARCHAR(64) CHARACTER SET utf8mb4 COLLATE ' . $collation . " UNIQUE); INSERT INTO unicode_unique VALUES (_utf8mb4 X'C39F'); INSERT INTO unicode_unique VALUES ('s'); SHOW WARNINGS"];
        }
        yield 'wide general collation' => ["SELECT STRCMP(CONVERT(_utf8mb4 X'C39F' USING utf16) COLLATE utf16_general_ci, CONVERT('s' USING utf16)), STRCMP(CONVERT(_utf8mb4 X'C3A9' USING ucs2) COLLATE ucs2_general_ci, CONVERT(_utf8mb4 X'65CC81' USING ucs2))"];
    }

    #[DataProvider('providerStatements')]
    public function testUnicodeOrderingMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
