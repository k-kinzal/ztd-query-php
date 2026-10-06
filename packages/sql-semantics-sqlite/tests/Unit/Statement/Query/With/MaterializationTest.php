<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\Materialization;

#[CoversClass(Materialization::class)]
#[Small]
final class MaterializationTest extends TestCase
{
    public function testCasesCarryTheKeywordsSqliteWrites(): void
    {
        self::assertSame(['MATERIALIZED', 'NOT MATERIALIZED'], array_map(static fn (Materialization $hint): string => $hint->value, Materialization::cases()));
        self::assertSame(Materialization::NotMaterialized, Materialization::from('NOT MATERIALIZED'));
    }
}
