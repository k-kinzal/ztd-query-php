<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\Scope;
use SqlSemantics\Platform\MySql\Dialect as MySqlDialect;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;

#[CoversClass(Scope::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[Small]
final class ScopeTest extends TestCase
{
    public function testWithAddsTheNamesOfAnInnerClauseAndKeepsTheScopeWithoutThem(): void
    {
        $outer = new Scope(['a']);
        self::assertSame(['a', 'b'], $outer->with(['b'])->names);
        self::assertSame(['a'], $outer->names);
        self::assertSame($outer, $outer->with([]));
        self::assertSame([], (new Scope())->names);
    }

    public function testContainsComparesOnePartNamesUnderTheRelationNamePolicy(): void
    {
        $scope = new Scope(['Recent']);
        self::assertTrue($scope->contains(['Recent'], MySqlDialect::MySql->platform()->names()));
        self::assertFalse($scope->contains(['recent'], MySqlDialect::MySql->platform()->names()));
        self::assertTrue($scope->contains(['recent'], SqliteDialect::Sqlite->platform()->names()));
        self::assertFalse($scope->contains(['app', 'Recent'], MySqlDialect::MySql->platform()->names()));
        self::assertFalse($scope->contains(['other'], MySqlDialect::MySql->platform()->names()));
    }
}
