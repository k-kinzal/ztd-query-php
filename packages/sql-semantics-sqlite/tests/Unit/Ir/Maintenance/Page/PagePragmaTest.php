<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PageLimit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragmaKind;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\RequestedPageSize;

#[CoversClass(PagePragma::class)]
#[Small]
final class PagePragmaTest extends TestCase
{
    public function testToStringDistinguishesQueryAndSettingRoles(): void
    {
        $schema = new Identifier('archive');

        self::assertSame('PRAGMA "main".page_size', (new PagePragma(PagePragmaKind::Size))->toString());
        self::assertSame('PRAGMA "archive".page_size(0)', (new PagePragma(PagePragmaKind::Size, $schema, new RequestedPageSize(0)))->toString());
        self::assertSame('PRAGMA "archive".max_page_count(4294967294)', (new PagePragma(PagePragmaKind::MaximumCount, $schema, new PageLimit('4294967294')))->toString());
    }

    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new PagePragma(PagePragmaKind::Count))->profileId());
    }
}
