<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlNativeUpsertProjector;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(PgSqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Dialect\PgSqlIdentifierQuoter::class)]
#[UsesClass(PgSqlLexerProfile::class)]
final class PgSqlNativeUpsertProjectorTest extends TestCase
{
    public function testKeepsIdentifiersInsideScalarSubqueryInTheirNativeScope(): void
    {
        $projector = new PgSqlNativeUpsertProjector();

        $result = $projector->project(
            'SELECT 1 AS "id", 0 AS "price"',
            'prices',
            ['id', 'price'],
            ['PRIMARY' => ['id']],
            ['price' => 'price + (SELECT price FROM price_list WHERE id = prices.id) + COALESCE(EXCLUDED.price, 0)'],
        );

        self::assertSame(
            'SELECT "__ztd_incoming"."id" AS "id", "__ztd_incoming"."price" AS "price", '
            . '(SELECT "__ztd_existing"."price" + (SELECT price FROM price_list WHERE id = prices.id) '
            . '+ COALESCE("__ztd_incoming"."price", 0) FROM "prices" AS "__ztd_existing" '
            . 'WHERE (("__ztd_existing"."id" = "__ztd_incoming"."id")) LIMIT 1) AS "__ztd_upsert_value_0" '
            . 'FROM (SELECT 1 AS "id", 0 AS "price") AS "__ztd_incoming"',
            $result,
        );
    }

    public function testBindsQualifiedAndQuotedIdentifiersWithoutChangingForeignNamespaces(): void
    {
        $projector = new PgSqlNativeUpsertProjector();

        $result = $projector->project(
            'SELECT 1 AS "id", 2 AS "odd""name", 3 AS "price"',
            'prices',
            ['id', 'odd"name', 'price'],
            ['PRIMARY' => ['id']],
            ['price' => 'EXCLUDED."odd""name" + prices.price + foreign.price + price(price) + CONSTANT'],
        );

        self::assertSame(
            'SELECT "__ztd_incoming"."id" AS "id", "__ztd_incoming"."odd""name" AS "odd""name", '
            . '"__ztd_incoming"."price" AS "price", '
            . '(SELECT "__ztd_incoming"."odd""name" + "__ztd_existing"."price" + foreign.price '
            . '+ price("__ztd_existing"."price") + CONSTANT FROM "prices" AS "__ztd_existing" '
            . 'WHERE (("__ztd_existing"."id" = "__ztd_incoming"."id")) LIMIT 1) AS "__ztd_upsert_value_0" '
            . 'FROM (SELECT 1 AS "id", 2 AS "odd""name", 3 AS "price") AS "__ztd_incoming"',
            $result,
        );
    }

    public function testMatchesColumnsAndIncomingNamespaceCaseInsensitively(): void
    {
        $projector = new PgSqlNativeUpsertProjector();

        $result = $projector->project(
            'SELECT 1 AS "ID", 2 AS "PRICE"',
            'ITEMS',
            ['ID', 'PRICE'],
            ['PRIMARY' => ['ID']],
            ['PRICE' => 'EXCLUDED.PRICE + PRICE + ITEMS.PRICE'],
        );

        self::assertSame(
            'SELECT "__ztd_incoming"."ID" AS "ID", "__ztd_incoming"."PRICE" AS "PRICE", '
            . '(SELECT "__ztd_incoming"."PRICE" + "__ztd_existing"."PRICE" + "__ztd_existing"."PRICE" '
            . 'FROM "ITEMS" AS "__ztd_existing" WHERE (("__ztd_existing"."ID" = "__ztd_incoming"."ID")) '
            . 'LIMIT 1) AS "__ztd_upsert_value_0" '
            . 'FROM (SELECT 1 AS "ID", 2 AS "PRICE") AS "__ztd_incoming"',
            $result,
        );
    }

    public function testReturnsIncomingProjectionWhenConflictMetadataIsUnavailable(): void
    {
        $projector = new PgSqlNativeUpsertProjector();

        self::assertSame(
            'SELECT 1 AS "id"',
            $projector->project('SELECT 1 AS "id"', 'items', ['id'], [], ['id' => 'EXCLUDED.id']),
        );
        self::assertSame(
            'SELECT 1 AS "id"',
            $projector->project('SELECT 1 AS "id"', 'items', ['id'], ['PRIMARY' => ['id']], []),
        );
    }

    public function testBindsUnqualifiedQuotedIdentifier(): void
    {
        $result = (new PgSqlNativeUpsertProjector())->project(
            'SELECT 1 AS "id", 2 AS "odd""name"',
            'items',
            ['id', 'odd"name'],
            ['PRIMARY' => ['id']],
            ['odd"name' => '"odd""name" + 1'],
        );

        self::assertStringContainsString(
            '(SELECT "__ztd_existing"."odd""name" + 1 FROM "items" AS "__ztd_existing"',
            $result,
        );
    }

    public function testBindsPartialConflictPredicateToExistingAndIncomingRows(): void
    {
        $result = (new PgSqlNativeUpsertProjector())->project(
            'SELECT \'alice@example.com\' AS "email", \'active\' AS "status", 1 AS "login_count"',
            'users',
            ['email', 'status', 'login_count'],
            ['users_active_email' => ['email']],
            ['login_count' => 'GREATEST(login_count, EXCLUDED.login_count)'],
            conflictPredicate: "status = 'active'",
        );

        self::assertStringContainsString(
            '"__ztd_existing"."email" = "__ztd_incoming"."email"',
            $result,
        );
        self::assertStringContainsString(
            '("__ztd_existing"."status" = \'active\') AND ("__ztd_incoming"."status" = \'active\')',
            $result,
        );
    }
    public function testConflictPredicateComparesEveryColumnOfEachKey(): void
    {
        self::assertSame(
            '(("e"."id" = "i"."id"))',
            (new PgSqlNativeUpsertProjector())->conflictPredicate(['PRIMARY' => ['id']], '"e"', '"i"'),
        );
    }

    public function testConflictPredicateIsFalseWhereThereIsNoKeyToCollideOn(): void
    {
        self::assertSame('FALSE', (new PgSqlNativeUpsertProjector())->conflictPredicate([], '"e"', '"i"'));
    }

    public function testBindExpressionSaysABareNameMeansTheRowAlreadyThere(): void
    {
        self::assertSame(
            '"__ztd_existing"."qty"',
            (new PgSqlNativeUpsertProjector())->bindExpression('qty', 'items', ['qty']),
        );
    }

    public function testBindExpressionSaysExcludedMeansTheIncomingRow(): void
    {
        self::assertSame(
            '"__ztd_incoming"."qty"',
            (new PgSqlNativeUpsertProjector())->bindExpression('excluded.qty', 'items', ['qty']),
        );
    }

    public function testBindExpressionLeavesANameInsideASubqueryAlone(): void
    {
        self::assertStringContainsString(
            'SELECT qty',
            (new PgSqlNativeUpsertProjector())->bindExpression('(SELECT qty FROM other)', 'items', ['qty']),
        );
    }

    public function testSubqueryTokenIndexesMarksEveryTokenInsideASubquery(): void
    {
        $tokens = SqlTokenStream::tokenize('a + (SELECT b)', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame([3, 4], array_keys((new PgSqlNativeUpsertProjector())->subqueryTokenIndexes($tokens)));
    }

    public function testSubqueryTokenIndexesMarksNothingWhereThereIsNoSubquery(): void
    {
        $tokens = SqlTokenStream::tokenize('a + 1', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame([], (new PgSqlNativeUpsertProjector())->subqueryTokenIndexes($tokens));
    }

    public function testIsIdentifierReportsABareWord(): void
    {
        $tokens = SqlTokenStream::tokenize('qty', PgSqlLexerProfile::create())->significantTokens();

        self::assertTrue((new PgSqlNativeUpsertProjector())->isIdentifier($tokens[0]));
    }

    public function testIsIdentifierIsFalseForALiteral(): void
    {
        $tokens = SqlTokenStream::tokenize('1', PgSqlLexerProfile::create())->significantTokens();

        self::assertFalse((new PgSqlNativeUpsertProjector())->isIdentifier($tokens[0]));
    }

    public function testIdentifierTakesTheQuotingOffAName(): void
    {
        $tokens = SqlTokenStream::tokenize('"order"', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame('order', (new PgSqlNativeUpsertProjector())->identifier($tokens[0]));
    }

    public function testIdentifierAnswersTheTokensOwnTextWhereItIsNotAName(): void
    {
        $tokens = SqlTokenStream::tokenize('1', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame('1', (new PgSqlNativeUpsertProjector())->identifier($tokens[0]));
    }

    public function testQualifiedWritesTheColumnAsBelongingToThatRow(): void
    {
        self::assertSame('"e"."qty"', (new PgSqlNativeUpsertProjector())->qualified('e', 'qty'));
    }
    public function testProjectLeavesAStatementWithNothingToUpdateAlone(): void
    {
        self::assertSame(
            'SELECT 1',
            (new PgSqlNativeUpsertProjector())->project('SELECT 1', 'items', ['qty'], [], []),
        );
    }

}
