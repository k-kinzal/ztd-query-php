<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Locking\ReachedScopes;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(ReachedScopes::class)]
#[Medium]
final class ReachedScopesTest extends TestCase
{
    public function testReadVisitsOnlyReferencedDefinitions(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering((new Platform())->productions($profile), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('WITH a AS (SELECT 1), b AS (SELECT 2) SELECT * FROM a');
        $scopes = (new ReachedScopes())->read($tree, $lowering);
        self::assertCount(2, $scopes);
        self::assertSame([], $scopes[0][1]);
    }

    public function testExtendRetainsEachDefinitionScopeBeforePublishingItsName(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering((new Platform())->productions($profile), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('WITH a AS (SELECT 1), b AS (SELECT * FROM a) SELECT * FROM b');
        $scopes = [[]];
        $definitions = [];
        $scope = (new ReachedScopes())->extend($tree->find('query_expression')[0], $lowering, 0, $scopes, $definitions);
        self::assertSame(['a' => 0, 'b' => 1], $scopes[$scope]);
        self::assertSame([], $scopes[$definitions[0][1]]);
        self::assertSame(['a' => 0], $scopes[$definitions[1][1]]);
    }
}
