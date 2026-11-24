<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Facade\SchemaMemberScope;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;

#[CoversClass(SchemaMemberScope::class)]
final class SchemaMemberScopeTest extends TestCase
{
    public function testBindKeepsClusterIdentitiesAndSelectsTheCreatingNamespace(): void
    {
        $outer = new AnalysisContext(
            new LanguageProfile(GrammarRelease::PostgreSql172),
            [new Name('pg_temp'), new Name('pg_catalog'), new Name('public')],
            currentDatabase: new Name('application'),
            tablespaces: [new Name('archive')],
            roles: [new Name('operator')],
        );
        $scope = (new SchemaMemberScope())->bind($outer, new Name('fresh'), []);

        self::assertSame(['pg_temp', 'pg_catalog', 'fresh', 'public'], array_map(static fn (Name $name): string => $name->value, $scope->searchPath));
        self::assertSame('fresh', $scope->declarationSchema->value);
        self::assertSame($outer->currentDatabase, $scope->currentDatabase);
        self::assertSame($outer->tablespaces, $scope->tablespaces);
        self::assertSame($outer->roles, $scope->roles);
    }
}
