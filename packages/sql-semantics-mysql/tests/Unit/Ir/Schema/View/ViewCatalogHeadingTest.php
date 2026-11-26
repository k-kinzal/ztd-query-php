<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewCatalogHeading;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(ViewCatalogHeading::class)]
#[Small]
final class ViewCatalogHeadingTest extends TestCase
{
    public function testExplicitRetainsLegacyAssignmentAndModernExactNames(): void
    {
        $heading = new ViewCatalogHeading();
        $legacy = $heading->explicit(' É', GrammarRelease::MySql5744)->name;
        $modern = $heading->explicit(' É', GrammarRelease::MySql910)->name;

        self::assertInstanceOf(RequestedResultAlias::class, $legacy);
        self::assertSame('É', $legacy->value);
        self::assertSame(' É', $legacy->requested);
        self::assertInstanceOf(Identifier::class, $modern);
        self::assertSame(' É', $modern->value);
    }

    public function testCheckAdmitsTheInheritedEmptyName(): void
    {
        $columns = [new ViewColumn(new Identifier(''))];
        $query = new Select([new ResultColumn(new IntegerLiteral('1'), new RequestedResultAlias("\x7f"))]);
        $heading = new ViewCatalogHeading();
        $heading->check($columns, $query);

        self::assertTrue($heading->usesImplicit($columns, $query->release));
    }

    public function testUsesImplicitOnlyWhenTheReleaseNeedsIt(): void
    {
        $heading = new ViewCatalogHeading();
        $columns = [new ViewColumn(new Identifier(''))];

        self::assertTrue($heading->usesImplicit($columns, GrammarRelease::MySql910));
        self::assertFalse($heading->usesImplicit($columns, GrammarRelease::MySql5651));
    }

    public function testLegacyIdentifiesTheTwoAssignmentProfiles(): void
    {
        $heading = new ViewCatalogHeading();

        self::assertTrue($heading->legacy(GrammarRelease::MySql5651));
        self::assertTrue($heading->legacy(GrammarRelease::MySql5744));
        self::assertFalse($heading->legacy(GrammarRelease::MySql8044));
    }
}
