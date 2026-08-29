<?php

declare(strict_types=1);

namespace Tests\Unit\Dialect;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Dialect\MySqlTypeSemantics;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(MySqlTypeSemantics::class)]
#[UsesClass(MySqlLexerProfile::class)]
final class MySqlTypeSemanticsTest extends TestCase
{
    public function testLeavesSqlUntouchedWithoutEnumColumns(): void
    {
        $semantics = new MySqlTypeSemantics();
        $tables = [
            'items' => [
                'rows' => [],
                'columns' => ['name'],
                'columnTypes' => ['name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR(255)')],
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','medium','large')")],
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')")],
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
                    'name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                    'size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')"),
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
                    'Name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
                    'Size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')"),
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')")],
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')")],
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
                'columnTypes' => ['Size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')")],
            ],
            'Other' => [
                'rows' => [],
                'columns' => ['Size'],
                'columnTypes' => ['Size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('low','high')")],
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
        $type = new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')");
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
                    'size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')"),
                    'name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')")],
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')")],
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
                    'size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')"),
                    'name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR(255)'),
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')")],
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, ' enum ( \'small\' , "large" ) ')],
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('it''s','back\\\\slash')")],
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
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('','it\x5c's')")],
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
                'columnTypes' => ['Size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('small','large')")],
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
                    'plain' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR'),
                    'wrong' => new ColumnDeclaration(ColumnTypeFamily::STRING, "SET('a')"),
                    'open' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('a'"),
                    'empty' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'ENUM()'),
                    'short' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM(')"),
                    'unquoted' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'ENUM(a)'),
                    'same_edges' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'ENUM(aba)'),
                    'mismatched' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('a\")"),
                    'valid' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('a','b')"),
                ],
            ],
        ];

        self::assertSame(
            "SELECT * FROM items WHERE plain > 'a' AND wrong > 'a' AND open > 'a' AND empty > 'a' AND short > 'a' AND unquoted > 'a' AND same_edges > 'a' AND mismatched > 'a' AND FIELD(valid, 'a', 'b') > FIELD('a', 'a', 'b')",
            $semantics->rewrite("SELECT * FROM items WHERE plain > 'a' AND wrong > 'a' AND open > 'a' AND empty > 'a' AND short > 'a' AND unquoted > 'a' AND same_edges > 'a' AND mismatched > 'a' AND valid > 'a'", $tables),
        );
    }
    public function testEnumColumnsAnswersWhatAnEnumerationMayHold(): void
    {
        [$qualified, ] = (new MySqlTypeSemantics())->enumColumns([
            'items' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('s','m')")],
            ],
        ]);

        self::assertSame(['s', 'm'], $qualified['items.size'] ?? null);
    }

    public function testEnumColumnsAnswersNothingForABareNameTwoTablesDisagreeAbout(): void
    {
        [, $unqualified] = (new MySqlTypeSemantics())->enumColumns([
            'a' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('s')")],
            ],
            'b' => [
                'rows' => [],
                'columns' => ['size'],
                'columnTypes' => ['size' => new ColumnDeclaration(ColumnTypeFamily::STRING, "ENUM('m')")],
            ],
        ]);

        self::assertSame(['size' => null], $unqualified);
    }

    public function testComparisonEditsRewritesBothSidesOfAnOrderingComparison(): void
    {
        $semantics = new MySqlTypeSemantics();
        $sql = "SELECT * FROM t WHERE size < 'm'";
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();

        $edits = $semantics->comparisonEdits($sql, $tokens, [], ['size' => ['s', 'm']]);

        self::assertCount(2, $edits);
    }

    public function testComparisonEditsLeavesAComparisonAgainstNoEnumerationAlone(): void
    {
        $semantics = new MySqlTypeSemantics();
        $sql = "SELECT * FROM t WHERE name < 'm'";
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();

        self::assertSame([], $semantics->comparisonEdits($sql, $tokens, [], ['size' => ['s', 'm']]));
    }

    public function testOrderByEditsRewritesAColumnOrderedByOnItsOwn(): void
    {
        $semantics = new MySqlTypeSemantics();
        $sql = 'SELECT * FROM t ORDER BY size';
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();

        self::assertCount(1, $semantics->orderByEdits($sql, $tokens, [], ['size' => ['s', 'm']]));
    }

    public function testOrderByEditsLeavesAStatementThatOrdersByNothingAlone(): void
    {
        $semantics = new MySqlTypeSemantics();
        $sql = 'SELECT * FROM t';
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();

        self::assertSame([], $semantics->orderByEdits($sql, $tokens, [], ['size' => ['s', 'm']]));
    }

    public function testColumnAtAnswersTheEnumerationNamedThere(): void
    {
        $semantics = new MySqlTypeSemantics();
        $sql = 'size';
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();

        $column = $semantics->columnAt($sql, $tokens, 0, [], ['size' => ['s', 'm']]);

        self::assertSame(['s', 'm'], $column['members'] ?? null);
    }

    public function testColumnAtIsNothingWhereNoEnumerationIsNamed(): void
    {
        $semantics = new MySqlTypeSemantics();
        $sql = 'name';
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();

        self::assertNull($semantics->columnAt($sql, $tokens, 0, [], ['size' => ['s', 'm']]));
    }

    public function testOrderedOperatorAtReadsAnOperatorWrittenAsTwoSymbols(): void
    {
        $tokens = SqlTokenStream::tokenize('<= 1', MySqlLexerProfile::create())->significantTokens();

        self::assertSame(['length' => 2], (new MySqlTypeSemantics())->orderedOperatorAt($tokens, 0));
    }

    public function testOrderedOperatorAtIsNothingForEquality(): void
    {
        $tokens = SqlTokenStream::tokenize('= 1', MySqlLexerProfile::create())->significantTokens();

        self::assertNull((new MySqlTypeSemantics())->orderedOperatorAt($tokens, 0));
    }

    public function testAddRankEditRewritesTheTokenAsItsPositionAmongTheMembers(): void
    {
        $edits = [];
        $token = SqlTokenStream::tokenize('size', MySqlLexerProfile::create())->significantTokens()[0];

        (new MySqlTypeSemantics())->addRankEdit($edits, $token, ['s', 'm']);

        self::assertSame("FIELD(size, 's', 'm')", $edits[0]['replacement'] ?? null);
    }

    public function testEnumMembersReadsWhatAnEnumDeclarationAllows(): void
    {
        self::assertSame(['s', 'm'], (new MySqlTypeSemantics())->enumMembers("ENUM('s','m')"));
    }

    public function testEnumMembersIsNothingForADeclarationThatIsNoEnumeration(): void
    {
        self::assertSame([], (new MySqlTypeSemantics())->enumMembers('VARCHAR(10)'));
    }

    public function testIsIdentifierReportsABareWord(): void
    {
        $tokens = SqlTokenStream::tokenize('size', MySqlLexerProfile::create())->significantTokens();

        self::assertTrue((new MySqlTypeSemantics())->isIdentifier($tokens[0]));
    }

    public function testIsIdentifierIsFalseForALiteral(): void
    {
        $tokens = SqlTokenStream::tokenize('1', MySqlLexerProfile::create())->significantTokens();

        self::assertFalse((new MySqlTypeSemantics())->isIdentifier($tokens[0]));
    }

    public function testIdentifierNameTakesTheQuotingOffAName(): void
    {
        $tokens = SqlTokenStream::tokenize('`size`', MySqlLexerProfile::create())->significantTokens();

        self::assertSame('size', (new MySqlTypeSemantics())->identifierName($tokens[0]));
    }

}
