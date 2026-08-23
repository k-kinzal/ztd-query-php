<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlTypeSemantics;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlTypeSemanticsTest extends TestCase
{
    public function testLeavesSqlUntouchedWithoutEnumColumns(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['name'],
                'columnTypes' => ['name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)')],
            ],
        ];
        $sql = "SELECT * FROM items WHERE name > 'a' ORDER BY name";

        self::assertSame($sql, $semantics->rewrite($sql, $tables));
        self::assertSame($sql, $semantics->rewrite($sql, []));
    }

    public function testRewritesEnumOrderingAndOrderedComparisonsToRanks(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','medium','large')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items WHERE FIELD(size, 'small', 'medium', 'large') > FIELD('small', 'small', 'medium', 'large') ORDER BY FIELD(size, 'small', 'medium', 'large') DESC",
            $semantics->rewrite("SELECT * FROM items WHERE size > 'small' ORDER BY size DESC", $tables),
        );
    }

    public function testRewritesQualifiedAndQuotedEnumColumns(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items WHERE FIELD(`items`.`size`, 'small', 'large') <= FIELD('large', 'small', 'large') ORDER BY FIELD(`items`.`size`, 'small', 'large')",
            $semantics->rewrite("SELECT * FROM items WHERE `items`.`size` <= 'large' ORDER BY `items`.`size`", $tables),
        );
    }

    public function testLeavesPlainStringsAndComplexOrderExpressionsUntouched(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['name', 'size'],
                'columnTypes' => [
                    'name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                    'size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')"),
                ],
            ],
        ];
        $sql = "SELECT * FROM items WHERE name > 'a' ORDER BY COALESCE(size, 'small')";

        self::assertSame($sql, $semantics->rewrite($sql, $tables));
    }

    public function testFindsEnumAfterNonEnumColumnsAndNonOrderedOccurrences(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'Items' => [
                'rows' => [],
                'columns' => ['Name', 'Size'],
                'columnTypes' => [
                    'Name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                    'Size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')"),
                ],
            ],
        ];

        self::assertSame(
            "SELECT Size FROM Items WHERE Name > 'a' AND FIELD(SIZE, 'small', 'large') > FIELD('small', 'small', 'large')",
            $semantics->rewrite("SELECT Size FROM Items WHERE Name > 'a' AND SIZE > 'small'", $tables),
        );
    }

    public function testRewritesEveryOrderedComparisonAndRejectsOtherOperands(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items WHERE FIELD(size, 'small', 'large') < FIELD('large', 'small', 'large') OR FIELD(size, 'small', 'large') >= FIELD('small', 'small', 'large') OR size = 'small' OR size <> 'large' OR size > 1 OR size >",
            $semantics->rewrite("SELECT * FROM items WHERE size < 'large' OR size >= 'small' OR size = 'small' OR size <> 'large' OR size > 1 OR size >", $tables),
        );
    }

    public function testComparisonScanContinuesAfterInvalidRightOperand(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items WHERE size > 1 OR FIELD(size, 'small', 'large') > FIELD('small', 'small', 'large')",
            $semantics->rewrite("SELECT * FROM items WHERE size > 1 OR size > 'small'", $tables),
        );
    }

    public function testQualifiedColumnsDoNotFallBackToAmbiguousOrUnrelatedTables(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'Items' => [
                'rows' => [],
                'columns' => ['Size'],
                'columnTypes' => ['Size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
            'Other' => [
                'rows' => [],
                'columns' => ['Size'],
                'columnTypes' => ['Size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('low','high')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM Items JOIN Other WHERE FIELD(ITEMS.SIZE, 'small', 'large') > FIELD('small', 'small', 'large') AND FIELD(other.size, 'low', 'high') < FIELD('high', 'low', 'high') AND missing.size > 'small' AND size > 'small'",
            $semantics->rewrite("SELECT * FROM Items JOIN Other WHERE ITEMS.SIZE > 'small' AND other.size < 'high' AND missing.size > 'small' AND size > 'small'", $tables),
        );
    }

    public function testMatchingEnumsAcrossTablesAllowUnqualifiedColumn(): void
    {
        $semantics = new MySqlTypeSemantics();
        $type = new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')");
        $tables = [
            'first' => ['rows' => [], 'columns' => ['size'], 'columnTypes' => ['size' => $type]],
            'second' => ['rows' => [], 'columns' => ['size'], 'columnTypes' => ['size' => $type]],
        ];

        self::assertSame(
            "SELECT * FROM first JOIN second WHERE FIELD(size, 'small', 'large') > FIELD('small', 'small', 'large')",
            $semantics->rewrite("SELECT * FROM first JOIN second WHERE size > 'small'", $tables),
        );
    }

    public function testOrderByRewritesOnlyStandaloneEnumItems(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size', 'name'],
                'columnTypes' => [
                    'size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')"),
                    'name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                ],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items ORDER BY FIELD(size, 'small', 'large'), name, FIELD(items.size, 'small', 'large') ASC LIMIT size",
            $semantics->rewrite('SELECT * FROM items ORDER BY size, name, items.size ASC LIMIT size', $tables),
        );
        self::assertSame(
            "SELECT * FROM items ORDER BY size + 1, FIELD(size, 'small', 'large') DESC",
            $semantics->rewrite('SELECT * FROM items ORDER BY size + 1, size DESC', $tables),
        );
    }

    public function testOrderByStopsAtEveryFollowingClause(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items ORDER BY FIELD(size, 'small', 'large') LIMIT size",
            $semantics->rewrite('SELECT * FROM items ORDER BY size LIMIT size', $tables),
        );
        self::assertSame(
            "SELECT * FROM items ORDER BY FIELD(size, 'small', 'large') FOR UPDATE, size",
            $semantics->rewrite('SELECT * FROM items ORDER BY size FOR UPDATE, size', $tables),
        );
        self::assertSame(
            "SELECT * FROM items ORDER BY FIELD(size, 'small', 'large') LOCK IN SHARE MODE, size",
            $semantics->rewrite('SELECT * FROM items ORDER BY size LOCK IN SHARE MODE, size', $tables),
        );
    }

    public function testOrderByContinuesAfterNestedExpressions(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items ORDER BY COALESCE(size, 'small'), FIELD(size, 'small', 'large') DESC",
            $semantics->rewrite("SELECT * FROM items ORDER BY COALESCE(size, 'small'), size DESC", $tables),
        );
        self::assertSame(
            "SELECT * FROM items WHERE id IN (SELECT id FROM items ORDER BY FIELD(size, 'small', 'large')) ORDER BY FIELD(size, 'small', 'large')",
            $semantics->rewrite('SELECT * FROM items WHERE id IN (SELECT id FROM items ORDER BY size) ORDER BY size', $tables),
        );
    }

    public function testOrderByScanStartsAfterByAndContinuesAfterUnrelatedOrderWord(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size', 'name'],
                'columnTypes' => [
                    'size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')"),
                    'name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                ],
            ],
        ];

        self::assertSame(
            "SELECT size, name FROM items ORDER BY FIELD(size, 'small', 'large')",
            $semantics->rewrite('SELECT size, name FROM items ORDER BY size', $tables),
        );
        self::assertSame(
            "SELECT ORDER FROM items ORDER BY FIELD(size, 'small', 'large')",
            $semantics->rewrite('SELECT ORDER FROM items ORDER BY size', $tables),
        );
    }

    public function testByWithoutOrderAndIncompleteOrderRemainUntouched(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
        ];

        self::assertSame('SELECT size AS by FROM items', $semantics->rewrite('SELECT size AS by FROM items', $tables));
        self::assertSame('SELECT * FROM items ORDER', $semantics->rewrite('SELECT * FROM items ORDER', $tables));
        self::assertSame('SELECT * FROM items AS BY size', $semantics->rewrite('SELECT * FROM items AS BY size', $tables));
    }

    public function testParsesCaseWhitespaceAndBothEnumQuoteStyles(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, ' enum ( \'small\' , "large" ) ')],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items WHERE FIELD(size, 'small', 'large') > FIELD('small', 'small', 'large')",
            $semantics->rewrite("SELECT * FROM items WHERE size > 'small'", $tables),
        );
    }

    public function testEscapesEnumMembersInGeneratedFieldExpressions(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('it''s','back\\\\slash')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items ORDER BY FIELD(size, 'it''s', 'back\\\\slash')",
            $semantics->rewrite('SELECT * FROM items ORDER BY size', $tables),
        );
    }

    public function testParsesEmptyAndBackslashEscapedEnumMembers(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('','it\x5c's')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items ORDER BY FIELD(size, '', 'it''s')",
            $semantics->rewrite('SELECT * FROM items ORDER BY size', $tables),
        );
    }

    public function testBacktickQuotedIdentifiersResolveByTokenKind(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'Items' => [
                'rows' => [],
                'columns' => ['Size'],
                'columnTypes' => ['Size' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
        ];

        self::assertSame(
            "SELECT * FROM Items ORDER BY FIELD(`Items`.`Size`, 'small', 'large')",
            $semantics->rewrite('SELECT * FROM Items ORDER BY `Items`.`Size`', $tables),
        );
    }

    public function testIgnoresMalformedEnumDefinitionsBeforeValidColumn(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['plain', 'wrong', 'open', 'empty', 'short', 'unquoted', 'same_edges', 'mismatched', 'valid'],
                'columnTypes' => [
                    'plain' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR'),
                    'wrong' => new ColumnType(ColumnTypeFamily::STRING, "SET('a')"),
                    'open' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('a'"),
                    'empty' => new ColumnType(ColumnTypeFamily::STRING, 'ENUM()'),
                    'short' => new ColumnType(ColumnTypeFamily::STRING, "ENUM(')"),
                    'unquoted' => new ColumnType(ColumnTypeFamily::STRING, 'ENUM(a)'),
                    'same_edges' => new ColumnType(ColumnTypeFamily::STRING, 'ENUM(aba)'),
                    'mismatched' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('a\")"),
                    'valid' => new ColumnType(ColumnTypeFamily::STRING, "ENUM('a','b')"),
                ],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items WHERE plain > 'a' AND wrong > 'a' AND open > 'a' AND empty > 'a' AND short > 'a' AND unquoted > 'a' AND same_edges > 'a' AND mismatched > 'a' AND FIELD(valid, 'a', 'b') > FIELD('a', 'a', 'b')",
            $semantics->rewrite("SELECT * FROM items WHERE plain > 'a' AND wrong > 'a' AND open > 'a' AND empty > 'a' AND short > 'a' AND unquoted > 'a' AND same_edges > 'a' AND mismatched > 'a' AND valid > 'a'", $tables),
        );
    }
}
