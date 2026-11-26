<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupKey;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;

#[CoversClass(GroupKey::class)]
#[Small]
final class GroupKeyTest extends TestCase
{
    public function testRetainsBuiltinCollation(): void
    {
        $key = new GroupKey(new ColumnReference(0), Collation::RightTrim);

        self::assertSame(Collation::RightTrim, $key->collation);
    }
}
