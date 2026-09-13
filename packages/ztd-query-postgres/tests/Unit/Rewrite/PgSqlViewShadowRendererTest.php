<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser;
use ZtdQuery\Platform\Postgres\PgSqlViewShadowRenderer;
use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Schema\ViewDefinitionSet;

#[CoversClass(PgSqlViewShadowRenderer::class)]
#[UsesClass(PgSqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\FromClause::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\RelationReference::class)]
final class PgSqlViewShadowRendererTest extends TestCase
{
    public function testRenderOrdersViewsAndUnqualifiesShadowedPostgreSqlRelations(): void
    {
        $views = new ViewDefinitionSet();
        $views->register('summary', new ViewDefinition('SELECT count(*) FROM public."active_users"', ['active_users']));
        $views->register('active_users', new ViewDefinition('SELECT * FROM public."users"', ['users']));

        self::assertSame(
            [
                'active_users' => 'SELECT * FROM "users"',
                'summary' => 'SELECT count(*) FROM "active_users"',
            ],
            (new PgSqlViewShadowRenderer())->render($views, ['users']),
        );
    }
}
