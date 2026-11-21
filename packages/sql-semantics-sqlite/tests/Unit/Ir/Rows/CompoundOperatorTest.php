<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundOperator;

#[CoversClass(CompoundOperator::class)]
#[Small]
final class CompoundOperatorTest extends TestCase
{
    public function testSpellingsPreserveTheSetAndBagOperations(): void
    {
        self::assertSame(['UNION ALL', 'UNION', 'INTERSECT', 'EXCEPT'], array_column(CompoundOperator::cases(), 'value'));
    }
}
