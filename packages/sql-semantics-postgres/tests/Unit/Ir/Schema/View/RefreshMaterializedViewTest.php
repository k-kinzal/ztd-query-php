<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\RefreshMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(RefreshMaterializedView::class)]
#[Small]
final class RefreshMaterializedViewTest extends TestCase
{
    public function testProfileIdUsesTheChosenNativeRelease(): void
    {
        $name = new ViewName(new Identifier('app'), new Identifier('report'));
        $refresh = new RefreshMaterializedView($name, true, GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $refresh->profileId());
    }

    public function testToStringUsesTheAbsoluteTargetAndExplicitPopulationPolicy(): void
    {
        $name = new ViewName(new Identifier('app'), new Identifier('report'));
        $refresh = new RefreshMaterializedView($name, true, GrammarRelease::PostgreSql166);

        self::assertSame('REFRESH MATERIALIZED VIEW "app"."report" WITH DATA', $refresh->toString());
    }
}
