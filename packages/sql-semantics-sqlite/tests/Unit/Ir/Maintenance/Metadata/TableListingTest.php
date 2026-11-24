<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableListing;

#[CoversClass(TableListing::class)]
#[Small]
final class TableListingTest extends TestCase
{
    public function testToStringDistinguishesNoFilterFromAnEmptyName(): void
    {
        self::assertSame('PRAGMA table_list', (new TableListing())->toString());
        self::assertSame('PRAGMA "temp".table_list("")', (new TableListing(new Identifier('temp'), new Identifier('')))->toString());
    }

    public function testProfileIdUsesThePinnedNativeProfile(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new TableListing())->profileId());
    }
}
