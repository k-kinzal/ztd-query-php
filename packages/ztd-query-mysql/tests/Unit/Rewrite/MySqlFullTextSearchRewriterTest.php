<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Rewrite\MySqlFullTextSearchRewriter;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(MySqlFullTextSearchRewriter::class)]
#[UsesClass(MySqlLexerProfile::class)]
final class MySqlFullTextSearchRewriterTest extends TestCase
{
    public function testRewritesNaturalLanguageExpressionIntoCteSafeRelevance(): void
    {
        $sql = "SELECT MATCH(title, body) AGAINST ('search terms') AS score FROM articles "
            . "WHERE MATCH(title, body) AGAINST ('search terms')";
        $result = (new MySqlFullTextSearchRewriter())->rewrite($sql);

        $expression = "(CASE WHEN LOCATE(LOWER(NULLIF(TRIM(CAST(('search terms') AS CHAR)), '')), "
            . "LOWER(CONCAT_WS(' ', COALESCE(CAST((title) AS CHAR), ''), "
            . "COALESCE(CAST((body) AS CHAR), '')))) > 0 THEN 1.0 ELSE 0.0 END)";
        self::assertSame(
            "SELECT $expression AS score FROM articles WHERE $expression",
            $result,
        );
    }

    #[DataProvider('providerSearchModifier')]
    public function testPreservesOnePreparedParameterWhileRemovingSearchModifier(string $modifier): void
    {
        $sql = "SELECT * FROM articles WHERE MATCH(`articles`.`title`) AGAINST (?$modifier)";
        $result = (new MySqlFullTextSearchRewriter())->rewrite($sql);

        self::assertSame(
            "SELECT * FROM articles WHERE (CASE WHEN LOCATE(LOWER(NULLIF(TRIM(CAST((?) AS CHAR)), '')), "
            . "LOWER(CONCAT_WS(' ', COALESCE(CAST((`articles`.`title`) AS CHAR), '')))) "
            . '> 0 THEN 1.0 ELSE 0.0 END)',
            $result,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSearchModifier(): iterable
    {
        yield 'natural language' => [' IN NATURAL LANGUAGE MODE'];
        yield 'boolean' => [' IN BOOLEAN MODE'];
        yield 'query expansion' => [' WITH QUERY EXPANSION'];
        yield 'natural expansion' => [' IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION'];
    }

    #[DataProvider('providerUnchangedSql')]
    public function testLeavesLiteralsCommentsAndMalformedExpressionsUntouched(string $sql): void
    {
        self::assertSame($sql, (new MySqlFullTextSearchRewriter())->rewrite($sql));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnchangedSql(): iterable
    {
        yield 'literal' => ["SELECT 'MATCH(title) AGAINST (search)'"];
        yield 'comment' => ['SELECT 1 /* MATCH(title) AGAINST (search) */'];
        yield 'keyword only' => ['SELECT MATCH'];
        yield 'missing columns parentheses' => ['SELECT MATCH title'];
        yield 'wrong columns delimiter' => ["SELECT MATCH + title) AGAINST ('search')"];
        yield 'empty columns' => ['SELECT MATCH()'];
        yield 'missing against' => ['SELECT MATCH(title)'];
        yield 'wrong against keyword' => ["SELECT MATCH(title) BEFORE ('search')"];
        yield 'missing query' => ['SELECT MATCH(title) AGAINST'];
        yield 'missing query parentheses' => ["SELECT MATCH(title) AGAINST 'search'"];
        yield 'empty query' => ['SELECT MATCH(title) AGAINST ()'];
    }

    public function testKeepsNestedExpressionsInsideTheirStructuralBoundaries(): void
    {
        $sql = 'SELECT MATCH(COALESCE(title, subtitle), body) '
            . "AGAINST (IF(flag IN (1), 'search', 'other')) FROM articles";

        self::assertSame(
            "SELECT (CASE WHEN LOCATE(LOWER(NULLIF(TRIM(CAST((IF(flag IN (1), 'search', 'other')) AS CHAR)), '')), "
            . "LOWER(CONCAT_WS(' ', COALESCE(CAST((COALESCE(title, subtitle)) AS CHAR), ''), "
            . "COALESCE(CAST((body) AS CHAR), '')))) > 0 THEN 1.0 ELSE 0.0 END) FROM articles",
            (new MySqlFullTextSearchRewriter())->rewrite($sql),
        );
    }

    public function testDoesNotTreatAnUnrecognizedInClauseAsASearchModifier(): void
    {
        $sql = 'SELECT MATCH(title) AGAINST (flag IN unknown_mode) FROM articles';

        self::assertSame(
            "SELECT (CASE WHEN LOCATE(LOWER(NULLIF(TRIM(CAST((flag IN unknown_mode) AS CHAR)), '')), "
            . "LOWER(CONCAT_WS(' ', COALESCE(CAST((title) AS CHAR), '')))) "
            . '> 0 THEN 1.0 ELSE 0.0 END) FROM articles',
            (new MySqlFullTextSearchRewriter())->rewrite($sql),
        );
    }

    public function testFindsAModeModifierAfterANestedQueryExpression(): void
    {
        $result = (new MySqlFullTextSearchRewriter())->rewrite(
            "SELECT MATCH(title) AGAINST (COALESCE(?, 'fallback') IN BOOLEAN MODE)",
        );

        self::assertStringContainsString("CAST((COALESCE(?, 'fallback')) AS CHAR)", $result);
        self::assertStringNotContainsString('BOOLEAN MODE', $result);
    }

    public function testKeepsQueryKeywordWhenItIsTheSearchExpression(): void
    {
        $result = (new MySqlFullTextSearchRewriter())->rewrite(
            'SELECT MATCH(title) AGAINST (QUERY)',
        );

        self::assertStringContainsString('CAST((QUERY) AS CHAR)', $result);
    }

    public function testTrimsWhitespaceInsideTheQueryParentheses(): void
    {
        $result = (new MySqlFullTextSearchRewriter())->rewrite(
            "SELECT MATCH(title) AGAINST (  'needle'  )",
        );

        self::assertStringContainsString("CAST(('needle') AS CHAR)", $result);
        self::assertStringNotContainsString("CAST((  'needle'  ) AS CHAR)", $result);
    }
    public function testExpressionEditReplacesTheWholeMatchAgainst(): void
    {
        $sql = "SELECT MATCH(title) AGAINST ('a') FROM t";
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        $match = $stream->significantTokens()[1];

        $edit = (new MySqlFullTextSearchRewriter())->expressionEdit($sql, $stream, $match);

        self::assertSame([7, 33], [$edit['start'] ?? null, $edit['end'] ?? null]);
    }

    public function testExpressionEditIsNothingWhereThereIsNoAgainst(): void
    {
        $sql = 'SELECT MATCH(title) FROM t';
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        $match = $stream->significantTokens()[1];

        self::assertNull((new MySqlFullTextSearchRewriter())->expressionEdit($sql, $stream, $match));
    }

    public function testQueryExpressionDropsTheSearchModeItWasToldToScoreWith(): void
    {
        self::assertSame(
            "'a'",
            (new MySqlFullTextSearchRewriter())->queryExpression("'a' IN BOOLEAN MODE"),
        );
    }

    public function testQueryExpressionKeepsAWholeExpressionThatSaysNoMode(): void
    {
        self::assertSame("'a'", (new MySqlFullTextSearchRewriter())->queryExpression(" 'a' "));
    }

    public function testIsSearchModeReportsTheWordThatOpensOne(): void
    {
        $token = SqlTokenStream::tokenize('BOOLEAN', MySqlLexerProfile::create())->significantTokens()[0];

        self::assertTrue(MySqlFullTextSearchRewriter::isSearchMode($token));
    }

    public function testIsSearchModeIsFalseForASearchTerm(): void
    {
        $token = SqlTokenStream::tokenize("'a'", MySqlLexerProfile::create())->significantTokens()[0];

        self::assertFalse(MySqlFullTextSearchRewriter::isSearchMode($token));
    }

}
