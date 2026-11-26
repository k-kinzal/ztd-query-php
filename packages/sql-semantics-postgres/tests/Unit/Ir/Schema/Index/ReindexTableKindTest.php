<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableKind;

#[CoversClass(ReindexTableKind::class)]
#[Small]
final class ReindexTableKindTest extends TestCase
{
    public function testOwnerKindsHaveDistinctIdentities(): void
    {
        self::assertSame('base-table', ReindexTableKind::BaseTable->value);
        self::assertSame('materialized-view', ReindexTableKind::MaterializedView->value);
    }
}
