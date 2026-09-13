<?php

declare(strict_types=1);

namespace Tests\Unit\Rewriting\Transformer\Select;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Platform\Sqlite\Rewriting\Transformer\Select\ShadowCteRenderer;

#[CoversClass(ShadowCteRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Rendering\CastTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Rendering\ValueExpressionRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Rendering\ValueLiteralRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteCastRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteValueRenderer::class)]
final class ShadowCteRendererTest extends TestCase
{
    public function testGenerateCteRendersTypedFixturesExecutableBySqlite(): void
    {
        $renderer = new ShadowCteRenderer(
            new \ZtdQuery\Platform\Sqlite\SqliteCastRenderer(),
            new \ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector(),
            new \ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter(),
            new \ZtdQuery\Platform\Sqlite\SqliteValueRenderer(),
        );
        $cte = $renderer->generateCte('users', [['id' => 7, 'name' => "O'Brien"], ['id' => 8, 'name' => null]], ['id', 'name'], [], []);
        $statement = (new PDO('sqlite::memory:'))->query('WITH ' . $cte . ' SELECT * FROM users ORDER BY id');
        self::assertNotFalse($statement);
        self::assertSame([['id' => 7, 'name' => "O'Brien"], ['id' => 8, 'name' => null]], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testGenerateCteRejectsEmptyUnknownSchemas(): void
    {
        $renderer = new ShadowCteRenderer(
            new \ZtdQuery\Platform\Sqlite\SqliteCastRenderer(),
            new \ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector(),
            new \ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter(),
            new \ZtdQuery\Platform\Sqlite\SqliteValueRenderer(),
        );
        $this->expectException(RuntimeException::class);
        $renderer->generateCte('unknown', [], [], [], []);
    }

    public function testWrapCteQuotesTheProvidedTableAndProjectsGeneratedColumns(): void
    {
        $renderer = new ShadowCteRenderer(
            new \ZtdQuery\Platform\Sqlite\SqliteCastRenderer(),
            new \ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector(),
            new \ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter(),
            new \ZtdQuery\Platform\Sqlite\SqliteValueRenderer(),
        );
        $cte = $renderer->wrapCte('"items"', 'SELECT 3 AS amount, NULL AS total', ['amount', 'total'], ['total' => '(amount * 2)']);
        $statement = (new PDO('sqlite::memory:'))->query('WITH ' . $cte . ' SELECT total FROM items');
        self::assertNotFalse($statement);
        self::assertSame(6, $statement->fetchColumn());
    }

    public function testRenderRowPreservesColumnOrderAndMissingValues(): void
    {
        $renderer = new ShadowCteRenderer(
            new \ZtdQuery\Platform\Sqlite\SqliteCastRenderer(),
            new \ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector(),
            new \ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter(),
            new \ZtdQuery\Platform\Sqlite\SqliteValueRenderer(),
        );
        $sql = $renderer->renderRow(['id' => 9], ['name', 'id'], []);
        $statement = (new PDO('sqlite::memory:'))->query($sql);
        self::assertNotFalse($statement);
        self::assertSame([['name' => null, 'id' => 9]], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testRenderEmptyTableRetainsTypedZeroRowProjection(): void
    {
        $renderer = new ShadowCteRenderer(
            new \ZtdQuery\Platform\Sqlite\SqliteCastRenderer(),
            new \ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector(),
            new \ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter(),
            new \ZtdQuery\Platform\Sqlite\SqliteValueRenderer(),
        );
        self::assertSame('SELECT CAST(NULL AS INTEGER) AS "id", CAST(NULL AS TEXT) AS "name" WHERE 0', $renderer->renderEmptyTable(['id', 'name'], ['id' => new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, 'INTEGER')]));
    }

    public function testRenderFallbackNullCastUsesText(): void
    {
        $renderer = new ShadowCteRenderer(
            new \ZtdQuery\Platform\Sqlite\SqliteCastRenderer(),
            new \ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector(),
            new \ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter(),
            new \ZtdQuery\Platform\Sqlite\SqliteValueRenderer(),
        );
        self::assertSame('CAST(NULL AS TEXT)', $renderer->renderFallbackNullCast());
    }

}
