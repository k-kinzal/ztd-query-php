<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\EmptyIndexMetadata;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspectionKind;

#[CoversClass(IndexInspection::class)]
#[Small]
final class IndexInspectionTest extends TestCase
{
    public function testToStringEmitsAnAbsoluteIndexOrTheOriginalMissingScope(): void
    {
        $target = new IndexTarget(new Identifier('archive'), new Identifier('code "entry"'), new Identifier('orders'));
        $empty = new EmptyIndexMetadata(new Identifier('temp'), new Identifier('missing'));

        self::assertSame('PRAGMA "archive".index_xinfo("code ""entry""")', (new IndexInspection(IndexInspectionKind::Extended, $target))->toString());
        self::assertSame('PRAGMA "temp".index_info("missing")', (new IndexInspection(IndexInspectionKind::Basic, $empty))->toString());
        self::assertSame('PRAGMA index_info', (new IndexInspection(IndexInspectionKind::Basic))->toString());
    }

    public function testProfileIdUsesThePinnedSqliteProfile(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new IndexInspection(IndexInspectionKind::Basic))->profileId());
    }
}
