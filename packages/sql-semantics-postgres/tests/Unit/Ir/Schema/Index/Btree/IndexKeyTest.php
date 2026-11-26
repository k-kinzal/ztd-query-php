<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexKey;

#[CoversClass(IndexKey::class)]
#[Small]
final class IndexKeyTest extends TestCase
{
    public function testUsesTheNativeAscendingDefault(): void
    {
        self::assertSame(2, (new IndexKey(2))->slot);
        self::assertSame('ASC NULLS LAST', (new IndexKey(2))->order->value);
    }
}
