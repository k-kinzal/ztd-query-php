<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\MySqlSelectRelationParser;
use ZtdQuery\Platform\MySql\MySqlViewShadowRenderer;
use ZtdQuery\Schema\ViewDefinition;
use ZtdQuery\Schema\ViewDefinitionSet;

#[CoversClass(MySqlViewShadowRenderer::class)]
#[UsesClass(MySqlLexerProfile::class)]
#[UsesClass(MySqlSelectRelationParser::class)]
final class MySqlViewShadowRendererTest extends TestCase
{
    public function testOrdersViewsAndUnqualifiesShadowedMySqlRelations(): void
    {
        $views = new ViewDefinitionSet();
        $views->register('summary', new ViewDefinition('SELECT count(*) FROM app.`active_users`', ['active_users']));
        $views->register('active_users', new ViewDefinition('SELECT * FROM app.`users`', ['users']));

        self::assertSame(
            [
                'active_users' => 'SELECT * FROM `users`',
                'summary' => 'SELECT count(*) FROM `active_users`',
            ],
            (new MySqlViewShadowRenderer())->render($views, ['users']),
        );
    }
}
