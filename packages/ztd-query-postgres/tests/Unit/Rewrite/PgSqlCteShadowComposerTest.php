<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Parse\PgSqlWithPrefix;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlCteShadowComposer;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(PgSqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parse\PgSqlSelectRelationParser::class)]
#[UsesClass(PgSqlLexerProfile::class)]
final class PgSqlCteShadowComposerTest extends TestCase
{
    public function testIncludesTransitivelyReferencedShadowCtes(): void
    {
        self::assertSame(
            "WITH \"items\" AS (SELECT 1 AS id),\n\"item_view\" AS (SELECT * FROM items)\nSELECT * FROM item_view",
            (new PgSqlCteShadowComposer())->compose(
                'SELECT * FROM item_view',
                [
                    'items' => '"items" AS (SELECT 1 AS id)',
                    'item_view' => '"item_view" AS (SELECT * FROM items)',
                    'unrelated' => '"unrelated" AS (SELECT 2 AS id)',
                ],
            ),
        );
    }

    public function testAddsShadowsAfterRecursiveModifier(): void
    {
        $sql = 'WITH RECURSIVE tree AS (SELECT id FROM nodes UNION ALL SELECT n.id FROM nodes n JOIN tree t ON n.parent_id = t.id) SELECT * FROM tree';

        self::assertSame(
            "WITH RECURSIVE \"nodes\" AS (SELECT 1 AS id),\n tree AS (SELECT id FROM nodes UNION ALL SELECT n.id FROM nodes n JOIN tree t ON n.parent_id = t.id) SELECT * FROM tree",
            (new PgSqlCteShadowComposer())->compose($sql, ['nodes' => '"nodes" AS (SELECT 1 AS id)']),
        );
    }

    public function testPreservesUserCteThatOwnsThePhysicalTableName(): void
    {
        $sql = 'WITH users AS (SELECT 1 AS id), filtered AS (SELECT * FROM users) SELECT * FROM filtered';

        self::assertSame(
            $sql,
            (new PgSqlCteShadowComposer())->compose($sql, ['users' => '"users" AS (SELECT 2 AS id)']),
        );
    }

    public function testInjectsReferencedBaseTableBeforeUserCtes(): void
    {
        $sql = 'WITH filtered AS (SELECT * FROM users WHERE active) SELECT * FROM filtered';

        self::assertSame(
            "WITH \"users\" AS (SELECT 1 AS id),\n filtered AS (SELECT * FROM users WHERE active) SELECT * FROM filtered",
            (new PgSqlCteShadowComposer())->compose($sql, ['users' => '"users" AS (SELECT 1 AS id)']),
        );
    }

    public function testDoesNotTreatLiteralsCommentsOrIdentifierPrefixesAsReferences(): void
    {
        $sql = "SELECT 'users', superusers.id /* users */ FROM superusers";

        self::assertSame(
            $sql,
            (new PgSqlCteShadowComposer())->compose($sql, ['users' => '"users" AS (SELECT 1 AS id)']),
        );
    }

    public function testReadsQuotedAndColumnListedCteNames(): void
    {
        self::assertSame(
            ['first', 'second'],
            (new PgSqlWithPrefix())->declaredCteNames('WITH "first"(id) AS MATERIALIZED (SELECT 1), second AS (SELECT 2) SELECT * FROM second'),
        );
    }

    public function testCarriesTheCompleteCteHeaderOntoARewrittenDmlProjection(): void
    {
        $sql = 'WITH first AS (SELECT 1), second(id) AS (SELECT * FROM first) UPDATE users SET id = 2';

        self::assertSame(
            "WITH first AS (SELECT 1), second(id) AS (SELECT * FROM first)\nSELECT 2 AS id FROM users",
            (new PgSqlWithPrefix())->carryPrefix($sql, 'SELECT 2 AS id FROM users'),
        );
    }

    public function testMergesProjectionCtesIntoTheOriginalHeader(): void
    {
        self::assertSame(
            "WITH source AS (SELECT 1),\nprojected AS (SELECT * FROM source)\nSELECT * FROM projected",
            (new PgSqlWithPrefix())->carryPrefix(
                'WITH source AS (SELECT 1) INSERT INTO target SELECT * FROM source',
                'WITH projected AS (SELECT * FROM source) SELECT * FROM projected',
            ),
        );
    }

    public function testOrdersShadowCtesBeforeUserCtesThatReadThem(): void
    {
        self::assertSame(
            "WITH users AS (SELECT 1 AS id),\nchosen AS (SELECT id FROM users)\nSELECT * FROM users WHERE id IN (SELECT id FROM chosen)",
            (new PgSqlWithPrefix())->carryPrefix(
                'WITH chosen AS (SELECT id FROM users) UPDATE users SET id = 2',
                'WITH users AS (SELECT 1 AS id) SELECT * FROM users WHERE id IN (SELECT id FROM chosen)',
            ),
        );
    }

    public function testExtractsTheStatementFollowingTheCteHeader(): void
    {
        self::assertSame(
            'DELETE FROM users WHERE id IN (SELECT id FROM chosen)',
            (new PgSqlWithPrefix())->statementSql('WITH chosen AS (SELECT 1 AS id) DELETE FROM users WHERE id IN (SELECT id FROM chosen)'),
        );
    }

    public function testComposesAReferencedShadowWithoutAnExistingWithClause(): void
    {
        self::assertSame(
            "WITH users AS (SELECT 1 AS id)\nSELECT * FROM users",
            (new PgSqlCteShadowComposer())->compose(
                'SELECT * FROM users',
                ['users' => 'users AS (SELECT 1 AS id)'],
            ),
        );
    }

    public function testSkipsEntriesIndependentlyAndMatchesDeclaredNamesCaseInsensitively(): void
    {
        $composer = new PgSqlCteShadowComposer();

        self::assertSame(
            "WITH orders AS (SELECT 2 AS id)\nSELECT * FROM orders",
            $composer->compose(
                'SELECT * FROM orders',
                [
                    'users' => 'users AS (SELECT 1 AS id)',
                    'orders' => 'orders AS (SELECT 2 AS id)',
                ],
            ),
        );
        $declared = 'WITH users AS (SELECT 1 AS id) SELECT * FROM users';
        self::assertSame(
            $declared,
            $composer->compose($declared, ['Users' => 'Users AS (SELECT 2 AS id)']),
        );
        self::assertStringContainsString(
            'orders AS (SELECT 2 AS id)',
            $composer->compose(
                'WITH users AS (SELECT 1 AS id) SELECT * FROM users JOIN orders ON TRUE',
                [
                    'orders' => 'orders AS (SELECT 2 AS id)',
                    'Users' => 'Users AS (SELECT 3 AS id)',
                ],
            ),
        );
    }

    public function testHandlesAWithTokenWithoutFollowingHeaderTokens(): void
    {
        self::assertSame(
            "WITH shadow AS (SELECT 1),\n",
            (new PgSqlCteShadowComposer())->compose('WITH', ['WITH' => 'shadow AS (SELECT 1)']),
        );
    }

    public function testCarriesAnEmptyRewriteWithoutDereferencingMissingTokens(): void
    {
        self::assertSame(
            "WITH source AS (SELECT 1)\n",
            (new PgSqlWithPrefix())->carryPrefix(
                'WITH source AS (SELECT 1) UPDATE users SET id = 2',
                '',
            ),
        );
    }

    public function testMergesRecursiveHeadersAndPreservesLeadingComments(): void
    {
        self::assertSame(
            "/* lead */ WITH RECURSIVE projected AS (SELECT 2),\noriginal AS (SELECT 1)\nSELECT * FROM projected",
            (new PgSqlWithPrefix())->carryPrefix(
                '/* lead */ WITH RECURSIVE original AS (SELECT 1) UPDATE users SET id = 2',
                'WITH projected AS (SELECT 2) SELECT * FROM projected',
            ),
        );
    }

    public function testRecognizesRecursiveRewrittenHeaderDependencies(): void
    {
        self::assertSame(
            "WITH source AS (SELECT 1),\nprojected AS (SELECT * FROM source)\nSELECT * FROM projected",
            (new PgSqlWithPrefix())->carryPrefix(
                'WITH source AS (SELECT 1) INSERT INTO target SELECT * FROM source',
                'WITH RECURSIVE projected AS (SELECT * FROM source) SELECT * FROM projected',
            ),
        );
    }

    public function testComposesShadowOverSchemaQualifiedSelectSource(): void
    {
        self::assertSame(
            "WITH users AS (SELECT 1 AS id)\nSELECT * FROM \"users\"",
            (new PgSqlCteShadowComposer())->compose(
                'SELECT * FROM public."users"',
                ['users' => 'users AS (SELECT 1 AS id)'],
            ),
        );
    }
    public function testParseHeaderReadsWhatTheWithNamesAndWhereTheStatementStarts(): void
    {
        self::assertSame(
            ['names' => ['x'], 'statementOffset' => 21],
            (new PgSqlWithPrefix())->parseHeader('WITH x AS (SELECT 1) SELECT * FROM x'),
        );
    }

    public function testParseHeaderIsEmptyForAStatementThatOpensWithNoWith(): void
    {
        self::assertSame(
            ['names' => [], 'statementOffset' => null],
            (new PgSqlWithPrefix())->parseHeader('SELECT 1'),
        );
    }

    public function testFindAsIndexAnswersWhereTheAsIsWritten(): void
    {
        $tokens = SqlTokenStream::tokenize('x AS (SELECT 1)', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame(1, (new PgSqlWithPrefix())->findAsIndex($tokens, 1));
    }

    public function testFindAsIndexIsNothingWhereABareWordComesFirst(): void
    {
        $tokens = SqlTokenStream::tokenize('x y', PgSqlLexerProfile::create())->significantTokens();

        self::assertNull((new PgSqlWithPrefix())->findAsIndex($tokens, 1));
    }

    public function testIsSymbolReportsATokenBeingThatSymbol(): void
    {
        $tokens = SqlTokenStream::tokenize('(1)', PgSqlLexerProfile::create())->significantTokens();

        self::assertTrue((new PgSqlWithPrefix())->isSymbol($tokens[0], '('));
    }

    public function testIsSymbolIsFalsePastTheEndOfWhatWasWritten(): void
    {
        self::assertFalse((new PgSqlWithPrefix())->isSymbol(null, '('));
    }

    public function testReferencesIdentifierReportsAStatementNamingIt(): void
    {
        self::assertTrue((new PgSqlWithPrefix())->referencesIdentifier('SELECT * FROM users', 'users'));
    }

    public function testReferencesIdentifierReadsAQuotedNameAsTheSameName(): void
    {
        self::assertTrue((new PgSqlWithPrefix())->referencesIdentifier('SELECT * FROM "users"', 'users'));
    }

    public function testReferencesIdentifierIsFalseWhereTheStatementNamesSomethingElse(): void
    {
        self::assertFalse((new PgSqlWithPrefix())->referencesIdentifier('SELECT * FROM orders', 'users'));
    }

    public function testReferencesAnyIdentifierReportsAStatementNamingOneOfThem(): void
    {
        self::assertTrue(
            (new PgSqlWithPrefix())->referencesAnyIdentifier('SELECT * FROM users', ['orders', 'users']),
        );
    }

    public function testReferencesAnyIdentifierIsFalseWhereItNamesNoneOfThem(): void
    {
        self::assertFalse(
            (new PgSqlWithPrefix())->referencesAnyIdentifier('SELECT 1', ['orders', 'users']),
        );
    }

    public function testIdentifierNameTakesTheQuotingOffAName(): void
    {
        $tokens = SqlTokenStream::tokenize('"order"', PgSqlLexerProfile::create())->significantTokens();

        self::assertSame('order', (new PgSqlWithPrefix())->identifierName($tokens[0]));
    }

    public function testIdentifierNameIsNothingForATokenThatIsNotAName(): void
    {
        $tokens = SqlTokenStream::tokenize('1', PgSqlLexerProfile::create())->significantTokens();

        self::assertNull((new PgSqlWithPrefix())->identifierName($tokens[0]));
    }

    public function testDeclaredCteNamesAnswersWhatTheStatementDeclaresForItself(): void
    {
        self::assertSame(
            ['x'],
            (new PgSqlWithPrefix())->declaredCteNames('WITH x AS (SELECT 1) SELECT * FROM x'),
        );
    }

    public function testCarryPrefixKeepsWhatTheStatementDeclaredForItself(): void
    {
        self::assertStringContainsString(
            'x AS (SELECT 1)',
            (new PgSqlWithPrefix())->carryPrefix(
                'WITH x AS (SELECT 1) SELECT * FROM x',
                'SELECT * FROM x',
            ),
        );
    }

    public function testStatementSqlAnswersTheStatementWithoutItsHeader(): void
    {
        self::assertSame(
            'SELECT * FROM x',
            (new PgSqlWithPrefix())->statementSql('WITH x AS (SELECT 1) SELECT * FROM x'),
        );
    }
}
