<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithMaterialization;

#[CoversClass(WithMaterialization::class)]
#[Small]
final class WithMaterializationTest extends TestCase
{
    public function testCasesIdentifyNativePolicies(): void
    {
        self::assertSame(['', 'MATERIALIZED', 'NOT MATERIALIZED'], array_column(WithMaterialization::cases(), 'value'));
    }
}
