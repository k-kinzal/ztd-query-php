<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewName;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewRemovalKind;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(DropView::class)]
#[Medium]
final class DropViewTest extends TestCase
{
    public function testProfileIdIdentifiesTheSelectedRelease(): void
    {
        $name = new ViewName(new Identifier('public'), new Identifier('daily'));
        $drop = new DropView(ViewRemovalKind::Ordinary, [$name], release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $drop->profileId());
    }

    public function testToStringRetainsTheKindOrderedNamesAndBothPolicies(): void
    {
        $first = new ViewName(new Identifier('other'), new Identifier('daily'));
        $second = new ViewName(new Identifier('public'), new Identifier('daily'));
        $drop = new DropView(ViewRemovalKind::Materialized, [$first, $second], true, true, GrammarRelease::PostgreSql166);

        self::assertSame([$first, $second], $drop->targets);
        self::assertSame('DROP MATERIALIZED VIEW IF EXISTS "other"."daily", "public"."daily" CASCADE', $drop->toString());
    }
}
