<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspectionKind;

#[CoversClass(IndexInspectionKind::class)]
#[Small]
final class IndexInspectionKindTest extends TestCase
{
    public function testCasesIdentifyTheTwoNativeIndexMetadataReads(): void
    {
        self::assertSame(['index_info', 'index_xinfo'], array_column(IndexInspectionKind::cases(), 'value'));
    }
}
