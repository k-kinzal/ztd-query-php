<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Lowering\StatementRoutes;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(StatementRoutes::class)]
#[Small]
final class StatementRoutesTest extends TestCase
{
    public function testRoutesCoverEveryStatementOfBothReleases(): void
    {
        $platform = new Platform();
        $all = [...$platform->productions(new LanguageProfile(GrammarRelease::PostgreSql166))->all(), ...$platform->productions(new LanguageProfile(GrammarRelease::PostgreSql172))->all()];
        $expected = array_values(array_unique(array_filter($all, static fn (string $signature): bool => str_starts_with($signature, 'stmt: '))));
        $routed = array_keys(StatementRoutes::ROUTES);
        sort($expected);
        sort($routed);
        self::assertSame($expected, $routed);
    }
}
