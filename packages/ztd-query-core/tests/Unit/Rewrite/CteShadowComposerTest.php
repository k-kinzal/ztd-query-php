<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\CteShadowComposer;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(CteShadowComposer::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenStream::class)]
final class CteShadowComposerTest extends TestCase
{
    public function testAddsShadowsAfterRecursiveModifier(): void
    {
        $sql = 'WITH RECURSIVE tree AS (SELECT id FROM nodes UNION ALL SELECT n.id FROM nodes n JOIN tree t ON n.parent_id = t.id) SELECT * FROM tree';

        self::assertSame(
            "WITH RECURSIVE \"nodes\" AS (SELECT 1 AS id),\n tree AS (SELECT id FROM nodes UNION ALL SELECT n.id FROM nodes n JOIN tree t ON n.parent_id = t.id) SELECT * FROM tree",
            (new CteShadowComposer())->compose($sql, ['nodes' => '"nodes" AS (SELECT 1 AS id)']),
        );
    }

    public function testPreservesUserCteThatOwnsThePhysicalTableName(): void
    {
        $sql = 'WITH users AS (SELECT 1 AS id), filtered AS (SELECT * FROM users) SELECT * FROM filtered';

        self::assertSame(
            $sql,
            (new CteShadowComposer())->compose($sql, ['users' => '"users" AS (SELECT 2 AS id)']),
        );
    }

    public function testInjectsReferencedBaseTableBeforeUserCtes(): void
    {
        $sql = 'WITH filtered AS (SELECT * FROM users WHERE active) SELECT * FROM filtered';

        self::assertSame(
            "WITH \"users\" AS (SELECT 1 AS id),\n filtered AS (SELECT * FROM users WHERE active) SELECT * FROM filtered",
            (new CteShadowComposer())->compose($sql, ['users' => '"users" AS (SELECT 1 AS id)']),
        );
    }

    public function testDoesNotTreatLiteralsCommentsOrIdentifierPrefixesAsReferences(): void
    {
        $sql = "SELECT 'users', superusers.id /* users */ FROM superusers";

        self::assertSame(
            $sql,
            (new CteShadowComposer())->compose($sql, ['users' => '"users" AS (SELECT 1 AS id)']),
        );
    }

    public function testReadsQuotedAndColumnListedCteNames(): void
    {
        self::assertSame(
            ['first', 'second'],
            (new CteShadowComposer())->declaredCteNames('WITH "first"(id) AS MATERIALIZED (SELECT 1), second AS (SELECT 2) SELECT * FROM second'),
        );
    }

    public function testCarriesTheCompleteCteHeaderOntoARewrittenDmlProjection(): void
    {
        $sql = 'WITH first AS (SELECT 1), second(id) AS (SELECT * FROM first) UPDATE users SET id = 2';

        self::assertSame(
            "WITH first AS (SELECT 1), second(id) AS (SELECT * FROM first)\nSELECT 2 AS id FROM users",
            (new CteShadowComposer())->carryPrefix($sql, 'SELECT 2 AS id FROM users'),
        );
    }

    public function testMergesProjectionCtesIntoTheOriginalHeader(): void
    {
        self::assertSame(
            "WITH source AS (SELECT 1),\nprojected AS (SELECT * FROM source)\nSELECT * FROM projected",
            (new CteShadowComposer())->carryPrefix(
                'WITH source AS (SELECT 1) INSERT INTO target SELECT * FROM source',
                'WITH projected AS (SELECT * FROM source) SELECT * FROM projected',
            ),
        );
    }

    public function testOrdersShadowCtesBeforeUserCtesThatReadThem(): void
    {
        self::assertSame(
            "WITH users AS (SELECT 1 AS id),\nchosen AS (SELECT id FROM users)\nSELECT * FROM users WHERE id IN (SELECT id FROM chosen)",
            (new CteShadowComposer())->carryPrefix(
                'WITH chosen AS (SELECT id FROM users) UPDATE users SET id = 2',
                'WITH users AS (SELECT 1 AS id) SELECT * FROM users WHERE id IN (SELECT id FROM chosen)',
            ),
        );
    }

    public function testExtractsTheStatementFollowingTheCteHeader(): void
    {
        self::assertSame(
            'DELETE FROM users WHERE id IN (SELECT id FROM chosen)',
            (new CteShadowComposer())->statementSql('WITH chosen AS (SELECT 1 AS id) DELETE FROM users WHERE id IN (SELECT id FROM chosen)'),
        );
    }
}
