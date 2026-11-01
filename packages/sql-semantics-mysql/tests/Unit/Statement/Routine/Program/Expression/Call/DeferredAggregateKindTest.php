<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind;

#[CoversClass(DeferredAggregateKind::class)]
#[Small]
final class DeferredAggregateKindTest extends TestCase
{
    public function testTheRetainedFormsHaveDistinctSqlNames(): void
    {
        self::assertSame(['GROUP_CONCAT', 'JSON_OBJECTAGG'], array_column(DeferredAggregateKind::cases(), 'value'));
    }
}
