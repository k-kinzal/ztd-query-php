<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Reporter\Html\CatalogIndex;
use SqlCatalog\Reporter\Html\CatalogStatistics;
use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\Page\NamespacePage;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\Scope;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(NamespacePage::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(CatalogIndex::class)]
#[UsesClass(CatalogStatistics::class)]
#[UsesClass(HtmlText::class)]
#[UsesClass(ReportSite::class)]
#[UsesClass(Scope::class)]
#[UsesClass(Severity::class)]
#[UsesClass(StatementKind::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class NamespacePageTest extends TestCase
{
    public function testRenderListsEveryNamespaceWithWhatIsDeclaredInIt(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'helper', 'pdo.query'), []),
        ]);
        $page = (new NamespacePage())->render(new ReportSite($catalog));

        self::assertStringContainsString('<h1>Namespaces<span class="count">2 namespaces</span></h1>', $page);
        self::assertStringContainsString('<h2 id="ns-global-namespace"><code>(global namespace)</code>', $page);
        self::assertStringContainsString('<h2 id="ns-app"><code>App</code>', $page);
        self::assertStringContainsString('No statement was found.', (new NamespacePage())->render(new ReportSite(new Catalog())));
    }

    public function testSectionLeadsFromAClassToItsPageAndFromAFunctionToItsFile(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 1, 'R::find', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('src/b.php', 2, 'helper', 'pdo.query'), []),
            new CatalogEntry('a3', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('src/c.php', 2, '{main}', 'pdo.query'), []),
        ];
        $section = (new NamespacePage())->section(new ReportSite(new Catalog($entries)), '', $entries);

        self::assertStringContainsString('<a class="anchor" href="statements.html?namespace=">All statements</a>', $section);
        self::assertStringContainsString('<tr><td><a class="mono" href="classes/r.html">R</a></td><td class="tight muted">class</td><td><a class="muted" href="files/src-a-php.html">src/a.php</a></td><td class="num">1</td><td class="num">1</td>', $section);
        self::assertStringContainsString('<a class="mono" href="files/src-b-php.html#fn-helper">helper</a></td><td class="tight muted">function</td>', $section);
        self::assertStringContainsString('<a class="mono" href="statements.html?function=%7Bmain%7D">top-level code</a></td><td class="tight muted">top-level code</td>', $section);
    }

    public function testMembersListClassesBeforeFunctions(): void
    {
        $entries = [
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'alpha', 'pdo.query'), []),
            new CatalogEntry('a2', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 2, 'Zed::run', 'pdo.query'), []),
            new CatalogEntry('a3', StatementKind::Select, TextPattern::fromText('SELECT 3'), [], [], new CallSite('a.php', 3, 'Zed::go', 'pdo.query'), []),
        ];

        self::assertSame(['Zed', 'alpha'], array_keys((new NamespacePage())->members($entries)));
    }

    public function testAnchorsNameEveryNamespace(): void
    {
        $catalog = new Catalog([
            new CatalogEntry('a1', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\R::find', 'pdo.query'), []),
        ]);

        self::assertSame([['App', 'ns-app']], (new NamespacePage())->anchors(new ReportSite($catalog)));
    }
}
