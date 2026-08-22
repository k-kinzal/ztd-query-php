<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser;
use ZtdQuery\Platform\Sqlite\SqliteViewShadowRenderer;
use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(SqliteViewShadowRenderer::class)]
#[UsesClass(SqliteSelectRelationParser::class)]
#[UsesClass(ViewDefinition::class)]
#[UsesClass(ViewDefinitionSet::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenKind::class)]
#[UsesClass(SqlTokenStream::class)]
final class SqliteViewShadowRendererTest extends TestCase
{
    public function testOrdersViewsAndUnqualifiesShadowedSqliteRelations(): void
    {
        $views = new ViewDefinitionSet();
        $views->register('summary', new ViewDefinition('SELECT count(*) FROM main.[active_users]', ['active_users']));
        $views->register('active_users', new ViewDefinition('SELECT * FROM main.[users]', ['users']));

        self::assertSame(
            [
                'active_users' => 'SELECT * FROM [users]',
                'summary' => 'SELECT count(*) FROM [active_users]',
            ],
            (new SqliteViewShadowRenderer())->render($views, ['users']),
        );
    }
}
